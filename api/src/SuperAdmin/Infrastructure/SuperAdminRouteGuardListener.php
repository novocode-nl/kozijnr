<?php

namespace App\SuperAdmin\Infrastructure;

use App\Tenancy\Infrastructure\TenantResolverListener;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Keeps every `/api/admin/*` route (super-admin login + management API)
 * exclusive to the reserved "admin" subdomain, building directly on
 * KOZ-6's tenant resolution rather than a separate host-regex check:
 * `/api/admin/*` is only reachable when TenantResolverListener has marked
 * the request as being on the admin domain (ADMIN_REQUEST_ATTRIBUTE).
 * Anywhere else — a known tenant subdomain, an unknown subdomain, or the
 * bare main domain — it 404s exactly like any other unknown route would;
 * it does not exist there, at all, not even to reject a login attempt
 * with 401/403. That would leak the fact that the endpoint exists
 * elsewhere.
 *
 * Rework (KOZ-8): originally this only required the *absence* of a
 * resolved tenant, so `/api/admin/*` was reachable from the bare main
 * domain. Functional review flipped this: "admin.kozijnr.nl" is THE place
 * where super-admin business happens, mirroring how a tenant's own
 * business lives exclusively under its own subdomain rather than also
 * being reachable from the bare domain — so the bare main domain no
 * longer grants admin access either, only the admin subdomain does.
 *
 * Runs after TenantResolverListener (priority 100) so the admin/tenant
 * attributes are already set, but before the security firewall (priority
 * 8) so a non-admin host never even reaches the super-admin authenticator.
 */
final class SuperAdminRouteGuardListener implements EventSubscriberInterface
{
    private const GUARDED_PATH_PREFIX = '/api/admin';

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 90],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();

        if (!str_starts_with($request->getPathInfo(), self::GUARDED_PATH_PREFIX)) {
            return;
        }

        if ($request->attributes->get(TenantResolverListener::ADMIN_REQUEST_ATTRIBUTE) !== true) {
            throw new NotFoundHttpException('Super-admin routes are only available on the admin subdomain.');
        }
    }
}
