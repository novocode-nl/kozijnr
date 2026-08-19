<?php

namespace App\Tenancy\Infrastructure\Valet;

/**
 * Registers a single Laravel Valet proxy mapping (KOZ-12): a *.test domain
 * pointed at a `http://127.0.0.1:<port>` target, standing in for a wildcard
 * subdomain that Valet's `proxy` driver does not support.
 *
 * Abstracted behind an interface purely so TenantValetProxyListener can be
 * unit tested with a stub/mock instead of ever shelling out to the real
 * `valet` binary from a test run — see SymfonyProcessValetProxyRunner for
 * the real implementation.
 */
interface ValetProxyRunnerInterface
{
    /**
     * @param string $domain the fully-qualified *.test domain to register,
     *                        e.g. "acme.kozijnr.test"
     * @param string $target the target the domain should proxy to, e.g.
     *                        "http://127.0.0.1:8000"
     */
    public function proxy(string $domain, string $target): void;
}
