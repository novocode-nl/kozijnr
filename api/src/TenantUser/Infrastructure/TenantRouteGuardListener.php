<?php

namespace App\TenantUser\Infrastructure;

use App\Tenancy\Infrastructure\TenantResolverListener;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Keeps tenant-user routes exclusive to an actually-resolved tenant
 * subdomain, mirroring AdminRouteGuardListener's approach for
 * `/api/admin/*`. On the bare main domain or the admin subdomain they 404
 * like any unknown route — this also prevents a confusing "table does not
 * exist" failure, since the tenant schema these routes depend on doesn't
 * exist outside a tenant schema at all.
 *
 * Runs after TenantResolverListener (priority 100) so its attribute is
 * already set, but before the security firewall (priority 8).
 */
final class TenantRouteGuardListener implements EventSubscriberInterface
{
    private const GUARDED_PATH_PREFIXES = ['/api/login', '/api/me', '/api/logout'];

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
        $path = $request->getPathInfo();

        $isGuarded = false;
        foreach (self::GUARDED_PATH_PREFIXES as $prefix) {
            if (str_starts_with($path, $prefix)) {
                $isGuarded = true;
                break;
            }
        }

        if (!$isGuarded) {
            return;
        }

        if ($request->attributes->get(TenantResolverListener::REQUEST_ATTRIBUTE) === null) {
            throw new NotFoundHttpException('Tenant-user routes are only available on a tenant subdomain.');
        }
    }
}
