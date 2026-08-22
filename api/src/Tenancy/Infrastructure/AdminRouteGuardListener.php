<?php

namespace App\Tenancy\Infrastructure;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Keeps every `/api/admin/*` route exclusive to the reserved "admin"
 * subdomain, building on TenantResolverListener's ADMIN_REQUEST_ATTRIBUTE
 * rather than a separate host-regex check. Anywhere else it 404s exactly
 * like any other unknown route — never a 401/403, which would leak that
 * the endpoint exists elsewhere. The bare main domain does not grant admin
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
