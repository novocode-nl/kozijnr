<?php

namespace App\Tenancy\Infrastructure\Valet;

use Psr\Log\LoggerInterface;
use Symfony\Component\Process\Exception\ExceptionInterface as ProcessExceptionInterface;
use Symfony\Component\Process\Process;

/**
 * Real ValetProxyRunnerInterface implementation (KOZ-12): shells out to the
 * host's `valet proxy <domain> <target>` CLI command.
 *
 * Deliberately never lets a failure here escape to the caller
 * (TenantValetProxyListener runs from a Doctrine postPersist event fired
 * mid-flush — an uncaught exception there would abort/roll back the flush
 * that just persisted the tenant, i.e. a missing/broken local Valet install
 * would break tenant provisioning itself). Any failure — `valet` not on
 * PATH, Valet not installed, a proxy that already exists, whatever — is
 * logged as a warning and swallowed instead, so this is best-effort dev
 * convenience only, never a hard dependency of tenant provisioning.
 */
final class SymfonyProcessValetProxyRunner implements ValetProxyRunnerInterface
{
    public function __construct(private readonly LoggerInterface $logger)
    {
    }

    public function proxy(string $domain, string $target): void
    {
        $process = new Process(['valet', 'proxy', $domain, $target]);

        try {
            $process->run();

            if (!$process->isSuccessful()) {
                $this->logger->warning(
                    'valet proxy registration failed for "{domain}" -> "{target}": {error}',
                    [
                        'domain' => $domain,
                        'target' => $target,
                        'error' => trim($process->getErrorOutput()) !== ''
                            ? trim($process->getErrorOutput())
                            : trim($process->getOutput()),
                    ],
                );
            }
        } catch (ProcessExceptionInterface $exception) {
            // e.g. the `valet` binary isn't on PATH at all (Valet not
            // installed) — same "best effort, never breaks provisioning"
            // reasoning as above.
            $this->logger->warning(
                'valet proxy registration for "{domain}" -> "{target}" could not be run: {message}',
                [
                    'domain' => $domain,
                    'target' => $target,
                    'message' => $exception->getMessage(),
                ],
            );
        }
    }
}
