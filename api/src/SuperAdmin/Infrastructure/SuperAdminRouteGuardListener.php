<?php

namespace App\SuperAdmin\Infrastructure;

use App\Tenancy\Infrastructure\TenantResolverListener;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Keeps every `/api/admin/*` route (super-admin login + management API)
 * off tenant subdomains, building directly on KOZ-6's tenant resolution
 * rather than a separate host-regex check: if TenantResolverListener
 * resolved a tenant for this request (i.e. we are on a known tenant
 * subdomain), the super-admin API 404s exactly like any other unknown
 * route would — it does not exist there, at all, not even to reject a
 * login attempt with 401/403. That would leak the fact that the endpoint
 * exists on a tenant subdomain in the first place.
 *
 * Runs after TenantResolverListener (priority 100) so `_tenant` is already
 * set, but before the security firewall (priority 8) so a tenant subdomain
 * never even reaches the super-admin authenticator.
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

        if ($request->attributes->get(TenantResolverListener::REQUEST_ATTRIBUTE) !== null) {
            throw new NotFoundHttpException('Super-admin routes are not available on a tenant subdomain.');
        }
    }
}
