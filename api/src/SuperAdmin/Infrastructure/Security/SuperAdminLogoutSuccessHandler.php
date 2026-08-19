<?php

namespace App\SuperAdmin\Infrastructure\Security;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Security\Http\Event\LogoutEvent;

/**
 * Returns a plain 204 for POST /api/admin/logout instead of the security
 * bundle's default redirect-to-"/" behaviour, which makes no sense for a
 * JSON API. Registered as a plain event listener (autoconfigured, no
 * config-file wiring needed) rather than via `security.firewalls.*.logout.success_handler`
 * — that option no longer exists on this Symfony version's logout config
 * tree.
 */
#[AsEventListener]
final class SuperAdminLogoutSuccessHandler
{
    public function __invoke(LogoutEvent $event): void
    {
        $event->setResponse(new JsonResponse(null, JsonResponse::HTTP_NO_CONTENT));
    }
}
