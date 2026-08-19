<?php

namespace App\Tenancy\Infrastructure;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Keeps every `/api/admin/*` route (admin login + tenant management API)
 * exclusive to the reserved "admin" subdomain, building directly on
 * TenantResolverListener rather than a separate host-regex check:
 * `/api/admin/*` is only reachable when TenantResolverListener has marked
 * the request as being on the admin domain (ADMIN_REQUEST_ATTRIBUTE).
 * Anywhere else — a known tenant subdomain, an unknown subdomain, or the
 * bare main domain — it 404s exactly like any other unknown route would;
 * it does not exist there, at all, not even to reject a login attempt
 * with 401/403. That would leak the fact that the endpoint exists
 * elsewhere.
 *
 * Lives in App\Tenancy (rework, KOZ-8, moved out of a former "SuperAdmin"
 * namespace): this listener is tenant-resolution logic through and
 * through — it builds directly on TenantResolverListener's own attribute
 * and has nothing to do with authenticating/authorizing a request once it
 * arrives (that stays the security firewall's job). Which routes it
 * happens to guard (currently only the admin ones) is incidental, not a
 * reason for a separate context.
 *
 * Originally this only required the *absence* of a resolved tenant, so
 * `/api/admin/*` was reachable from the bare main domain. Functional
 * review flipped this: "admin.kozijnr.nl" is THE place where admin
 * business happens, mirroring how a tenant's own business lives
 * exclusively under its own subdomain rather than also being reachable
 * from the bare domain — so the bare main domain no longer grants admin
 * access either, only the admin subdomain does.
 *
 * Runs after TenantResolverListener (priority 100) so the admin/tenant
 * attributes are already set, but before the security firewall (priority
 * 8) so a non-admin host never even reaches the admin authenticator.
 */
final class AdminRouteGuardListener implements EventSubscriberInterface
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
            throw new NotFoundHttpException('Admin routes are only available on the admin subdomain.');
        }
    }
}
