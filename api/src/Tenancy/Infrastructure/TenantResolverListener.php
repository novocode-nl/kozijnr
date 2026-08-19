<?php

namespace App\Tenancy\Infrastructure;

use App\Tenancy\Domain\Subdomain;
use App\Tenancy\Domain\TenantRepositoryInterface;
use Doctrine\DBAL\Connection;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Resolves the tenant for every incoming request from its subdomain and
 * points the Doctrine connection's search_path at that tenant's schema for
 * the rest of the request.
 *
 * Runs per-request, driven entirely by the current Request — no state is
 * cached on this listener between requests, so it stays safe even if the
 * container/connection were ever reused across requests by a persistent
 * worker runtime (FrankenPHP/RoadRunner-style). The search_path is reset to
 * `public` unconditionally at the start of every request before anything
 * else happens, so a request never inherits a schema left behind by a
 * previous one.
 */
final class TenantResolverListener implements EventSubscriberInterface
{
    public const REQUEST_ATTRIBUTE = '_tenant';

    public function __construct(
        private readonly Connection $connection,
        private readonly TenantRepositoryInterface $tenantRepository,
        private readonly string $baseDomain,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            // High priority: schema switching must happen before any other
            // kernel.request listener (e.g. controllers, security, other
            // subscribers) touches the database, so it needs to run early.
            KernelEvents::REQUEST => ['onKernelRequest', 100],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        // Always start clean: no fallback to whatever schema a previous
        // request (on a reused connection) might have left set.
        $this->connection->executeStatement('SET search_path TO public');

        $host = $event->getRequest()->getHost();
        $subdomain = Subdomain::extractFrom($host, $this->baseDomain);

        if ($subdomain === null) {
            // Main domain / no subdomain: request stays on the public schema.
            return;
        }

        $tenant = $this->tenantRepository->findBySubdomain($subdomain);

        if ($tenant === null) {
            throw new NotFoundHttpException(sprintf('Unknown tenant subdomain "%s".', $subdomain));
        }

        $this->connection->executeStatement(sprintf(
            'SET search_path TO %s, public',
            $this->connection->quoteSingleIdentifier($tenant->getSchemaName()),
        ));

        $event->getRequest()->attributes->set(self::REQUEST_ATTRIBUTE, $tenant);
    }
}
