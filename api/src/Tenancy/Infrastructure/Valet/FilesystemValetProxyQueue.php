<?php

namespace App\Tenancy\Infrastructure\Valet;

use Psr\Log\LoggerInterface;

/**
 * Real ValetProxyQueueInterface implementation (KOZ-12, rework): writes a
 * proxy request to its own JSON file under `<queueDirectory>/pending`,
 * instead of ever invoking `valet` itself — see ValetProxyQueueInterface's
 * docblock for why that split exists.
 *
 * `$queueDirectory` is `%app.tenancy.valet_queue_directory%`
 * (config/services.yaml), which defaults to
 * `%kernel.project_dir%/var/valet-proxy-queue` — i.e. `/app/var/...` inside
 * the backend container, which docker-compose.yml already bind-mounts as
 * `./api:/app`. A file written here is therefore immediately visible on the
 * host filesystem too, at `<worktree>/api/var/valet-proxy-queue/pending/`,
 * with no extra Docker volume needed. scripts/valet-sync.sh (run on the
 * host via `make valet-sync`) reads that same path directly.
 *
 * Never lets a failure here escape to the caller
 * (TenantValetProxyListener runs from a Doctrine postPersist event fired
 * mid-flush — an uncaught exception there would abort/roll back the flush
 * that just persisted the tenant). Any failure — the queue directory
 * couldn't be created, disk full, whatever — is logged as a warning and
 * swallowed instead, so this is best-effort dev convenience only, never a
 * hard dependency of tenant provisioning.
 */
final class FilesystemValetProxyQueue implements ValetProxyQueueInterface
{
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly string $queueDirectory,
    ) {
    }

    public function enqueue(string $domain, string $target): void
    {
        try {
            $pendingDirectory = $this->queueDirectory . '/pending';
            $this->ensureDirectoryExists($pendingDirectory);

            $payload = json_encode(
                ['domain' => $domain, 'target' => $target],
                JSON_THROW_ON_ERROR,
            );

            // A unique-per-call filename: two tenants created back to back
            // (or concurrently) must never collide and overwrite one
            // another's request.
            $filename = sprintf('%s-%s.json', (string) hrtime(true), bin2hex(random_bytes(4)));
            $path = $pendingDirectory . '/' . $filename;

            // Write to a temp file and rename (atomic on the same
            // filesystem), so scripts/valet-sync.sh — which may be polling
            // this directory concurrently — never observes a partially
            // written file.
            $tmpPath = $path . '.tmp';
            if (file_put_contents($tmpPath, $payload) === false) {
                throw new \RuntimeException(sprintf('Could not write "%s"', $tmpPath));
            }

            if (!rename($tmpPath, $path)) {
                throw new \RuntimeException(sprintf('Could not rename "%s" to "%s"', $tmpPath, $path));
            }
        } catch (\Throwable $exception) {
            // Same "best-effort, never breaks provisioning" contract as
            // before (see TenantValetProxyListener) — queueing a proxy
            // request is dev convenience, never a hard dependency.
            $this->logger->warning(
                'Could not enqueue a Valet proxy request for "{domain}" -> "{target}": {message}',
                [
                    'domain' => $domain,
                    'target' => $target,
                    'message' => $exception->getMessage(),
                ],
            );
        }
    }

    private function ensureDirectoryExists(string $directory): void
    {
        if (is_dir($directory)) {
            return;
        }

        // @-suppressed: a failure here (e.g. a path segment already exists
        // as a regular file) is expected and handled below by throwing,
        // which the caller turns into a logged warning + swallowed
        // failure — not something that should also surface as a raw PHP
        // warning.
        if (!@mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new \RuntimeException(sprintf('Could not create directory "%s"', $directory));
        }
    }
}
