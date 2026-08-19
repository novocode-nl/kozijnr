<?php

namespace App\Tenancy\Infrastructure\Valet;

use App\Tenancy\Domain\Tenant;
use Doctrine\ORM\Event\PostPersistEventArgs;

/**
 * Auto-registers a Valet proxy for a freshly-created tenant (KOZ-12), so it
 * has a working "<subdomain>.<base>.test" URL immediately, with no manual
 * step required — Valet's `proxy` driver has no wildcard-subdomain support,
 * so each tenant subdomain must be registered individually the moment it
 * starts existing.
 *
 * Deliberately a Doctrine `postPersist` listener on the Tenant entity
 * rather than something wired into ProvisionTenant (Application) itself:
 * ProvisionTenant is domain/application logic and must stay free of
 * Symfony/tooling dependencies (Process, this listener's own env checks).
 * Listening on the ORM lifecycle event instead means this fires for every
 * path that actually persists a Tenant (the admin API, `tenant:provision`,
 * ...) without ProvisionTenant ever knowing this listener exists — and it
 * costs Application nothing to add another entry point that creates
 * tenants later.
 *
 * Registered as a service in every environment (see config/services.yaml),
 * but only *tagged* as a `doctrine.event_listener` in
 * config/services_dev.yaml — so this class is never wired into the
 * postPersist event outside the local dev environment at all. The
 * environment check below is a second, defense-in-depth guard against ever
 * calling out to `valet` in a non-dev environment (e.g. if this were ever
 * mistakenly tagged in test/prod config), not the primary mechanism.
 */
final class TenantValetProxyListener
{
    private const DEV_ENVIRONMENT = 'dev';

    public function __construct(
        private readonly ValetProxyRunnerInterface $proxyRunner,
        private readonly string $environment,
        private readonly string $baseDomain,
        private readonly int $backendPort,
    ) {
    }

    public function postPersist(PostPersistEventArgs $args): void
    {
        $entity = $args->getObject();

        if (!$entity instanceof Tenant) {
            return;
        }

        if ($this->environment !== self::DEV_ENVIRONMENT) {
            return;
        }

        $domain = sprintf('%s.%s', $entity->getSubdomain(), $this->baseDomain);
        $target = sprintf('http://127.0.0.1:%d', $this->backendPort);

        $this->proxyRunner->proxy($domain, $target);
    }
}
