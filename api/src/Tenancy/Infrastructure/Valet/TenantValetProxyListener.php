<?php

namespace App\Tenancy\Infrastructure\Valet;

use App\Tenancy\Domain\Tenant;
use Doctrine\ORM\Event\PostPersistEventArgs;

/**
 * Queues a Valet proxy request for a freshly-created tenant (KOZ-12), so it
 * gets a working "<subdomain>.<base>.test" URL without a manual `valet
 * proxy` invocation per tenant — Valet's `proxy` driver has no
 * wildcard-subdomain support, so each tenant subdomain must be registered
 * individually the moment it starts existing.
 *
 * Rework note: this used to call `valet proxy` directly (via
 * ValetProxyRunnerInterface / SymfonyProcessValetProxyRunner), shelling out
 * to a subprocess from inside this listener. That could never work: this
 * code runs inside the backend Docker container, which has no `valet`
 * binary and no access to the host's Valet installation at all — no amount
 * of retrying or error handling fixes a binary that plain isn't reachable.
 * A host-side daemon that the container could call over the network was
 * considered and rejected too (it would need to run continuously and be
 * started/managed by hand — see README.md "Local domains via Laravel
 * Valet"). Instead, this listener now only *queues* the request (via
 * ValetProxyQueueInterface / FilesystemValetProxyQueue) by writing it to a
 * directory that's bind-mounted onto the host (docker-compose.yml's
 * `./api:/app`) — scripts/valet-sync.sh, run on the host (`make
 * valet-sync`), is what actually calls `valet proxy` for each queued
 * request. This works identically for every path that persists a Tenant —
 * the admin API, `tenant:provision`, ... — since it's still wired as a
 * Doctrine postPersist listener, not something ProvisionTenant needs to
 * know about.
 *
 * Deliberately a Doctrine `postPersist` listener on the Tenant entity
 * rather than something wired into ProvisionTenant (Application) itself:
 * ProvisionTenant is domain/application logic and must stay free of
 * Symfony/tooling dependencies (filesystem I/O, this listener's own env
 * checks). Listening on the ORM lifecycle event instead means this fires
 * for every path that actually persists a Tenant without ProvisionTenant
 * ever knowing this listener exists — and it costs Application nothing to
 * add another entry point that creates tenants later.
 *
 * Registered as a service in every environment (see config/services.yaml),
 * but only *tagged* as a `doctrine.event_listener` in
 * config/services_dev.yaml — so this class is never wired into the
 * postPersist event outside the local dev environment at all. The
 * environment check below is a second, defense-in-depth guard against ever
 * queueing a proxy request in a non-dev environment (e.g. if this were ever
 * mistakenly tagged in test/prod config), not the primary mechanism.
 */
final class TenantValetProxyListener
{
    private const DEV_ENVIRONMENT = 'dev';

    public function __construct(
        private readonly ValetProxyQueueInterface $proxyQueue,
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

        $this->proxyQueue->enqueue($domain, $target);
    }
}
