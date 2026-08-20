<?php

namespace App\Tenancy\Infrastructure\Valet;

/**
 * Queues a single Laravel Valet proxy *request* (KOZ-12, rework): a *.test
 * domain that should end up pointed at a `http://127.0.0.1:<port>` target,
 * standing in for a wildcard subdomain that Valet's `proxy` driver does not
 * support.
 *
 * Deliberately named "queue", not "runner": nothing implementing this
 * interface ever calls the `valet` binary itself. The backend runs inside a
 * Docker container that has neither the `valet` binary nor any access to
 * the host's Valet installation — that isn't a missing-error-handling bug,
 * it's a hard architectural fact, so no amount of retrying/logging inside
 * the container could ever make a direct `valet proxy` call from there
 * work. Instead, an implementation only records that a proxy is wanted;
 * scripts/valet-sync.sh, run on the host (`make valet-sync`), is what
 * actually calls `valet proxy` for each queued request. See
 * FilesystemValetProxyQueue for the real implementation and README.md
 * "Local domains via Laravel Valet" for the full reasoning, including why
 * this is a file-based queue rather than a host-side daemon process.
 *
 * Abstracted behind an interface purely so TenantValetProxyListener can be
 * unit tested with a stub/mock instead of ever touching the real queue
 * directory from a test run.
 */
interface ValetProxyQueueInterface
{
    /**
     * @param string $domain the fully-qualified *.test domain to register,
     *                        e.g. "acme.kozijnr.test"
     * @param string $target the target the domain should proxy to, e.g.
     *                        "http://127.0.0.1:8000"
     */
    public function enqueue(string $domain, string $target): void;
}
