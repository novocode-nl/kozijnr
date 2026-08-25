<?php

namespace App\Tenancy\Infrastructure\Controller;

use App\Tenancy\Application\GetTenantLocale;
use App\Tenancy\Domain\Tenant;
use App\Tenancy\Infrastructure\TenantResolverListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * GET /api/tenant-locale: the current tenant's default locale (KOZ-34),
 * publicly — no authenticated session, purely from the subdomain — so the
 * frontend's login screen (rendered before anyone is logged in) can render
 * in the tenant's configured language by default instead of a visitor's
 * previous/browser language. Same public-access shape as
 * GetTenantLoginImageController.
 */
final class GetTenantLocaleController
{
    public function __construct(private readonly GetTenantLocale $getTenantLocale)
    {
    }

    #[Route('/api/tenant-locale', name: 'tenant_locale_get', methods: ['GET'])]
    public function __invoke(Request $request): JsonResponse
    {
        /** @var Tenant $tenant */
        $tenant = $request->attributes->get(TenantResolverListener::REQUEST_ATTRIBUTE);

        return new JsonResponse(['defaultLocale' => ($this->getTenantLocale)($tenant)], JsonResponse::HTTP_OK);
    }
}
