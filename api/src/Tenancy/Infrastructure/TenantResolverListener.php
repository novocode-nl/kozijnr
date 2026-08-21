<?php

namespace App\Tenancy\Infrastructure;

use App\Tenancy\Domain\Subdomain;
use App\Tenancy\Domain\TenantRepositoryInterface;
use Doctrine\DBAL\Connection;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
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

    /**
     * Set (to `true`) on the request when the host resolved to the
     * reserved "admin" subdomain (admin.kozijnr.nl / admin.localhost) —
     * the tenant-independent super-admin domain. Mutually exclusive with
     * REQUEST_ATTRIBUTE: a request either resolves to a tenant, resolves
     * to the admin domain, or is on the bare main domain (neither
     * attribute set).
     */
    public const ADMIN_REQUEST_ATTRIBUTE = '_super_admin_domain';

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

        $request = $event->getRequest();
        $subdomain = Subdomain::extractFrom($request->getHost(), $this->baseDomain);

        if ($subdomain === null) {
            // Main domain / no subdomain: request stays on the public schema.
            return;
        }

        if ($subdomain === Subdomain::RESERVED_API) {
            // The API's own hostname (api.<base>, behind the nginx proxy
            // locally, the real api.<apex> in production). Browser clients
            // live on a sibling subdomain (admin.<base>, <tenant>.<base>)
            // and call this host cross-origin, so the browser-set Origin
            // header is what tells us which context they belong to. Origin
            // is set by the browser itself — page scripts can't forge it —
            // and CorsListener only ever lets sibling origins through, so
            // it's a trustworthy context carrier. A request without an
            // Origin (curl, server-to-server) or with a foreign/api origin
            // stays on the public schema.
            $subdomain = $this->subdomainFromOrigin($request);

            if ($subdomain === null || $subdomain === Subdomain::RESERVED_API) {
                return;
            }
        }

        if ($subdomain === Subdomain::RESERVED_ADMIN) {
            $request->attributes->set(self::ADMIN_REQUEST_ATTRIBUTE, true);

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

        $request->attributes->set(self::REQUEST_ATTRIBUTE, $tenant);
    }

    private function subdomainFromOrigin(Request $request): ?string
    {
        $origin = $request->headers->get('Origin');

        if ($origin === null) {
            return null;
        }

        $host = parse_url($origin, PHP_URL_HOST);

        return is_string($host) ? Subdomain::extractFrom($host, $this->baseDomain) : null;
    }
}
