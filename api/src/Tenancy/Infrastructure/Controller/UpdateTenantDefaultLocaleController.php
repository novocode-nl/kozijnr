<?php

namespace App\Tenancy\Infrastructure\Controller;

use App\Shared\Domain\Exception\ValidationException;
use App\Shared\Infrastructure\Http\ExceptionResponsePayload;
use App\Tenancy\Application\TenantSettings;
use App\Tenancy\Application\UpdateTenantDefaultLocale;
use App\Tenancy\Domain\Tenant;
use App\Tenancy\Infrastructure\TenantResolverListener;
use App\TenantUser\Domain\TenantUser;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * PATCH /api/settings/locale: sets the current tenant's default locale
 * (KOZ-34). Same authorization/tenant-resolution shape as
 * GetTenantSettingsController — see that class's doc.
 */
final class UpdateTenantDefaultLocaleController
{
    public function __construct(private readonly UpdateTenantDefaultLocale $updateTenantDefaultLocale)
    {
    }

    #[Route('/api/settings/locale', name: 'tenant_settings_update_locale', methods: ['PATCH'])]
    #[IsGranted(TenantUser::ROLE_TENANT_ADMIN)]
    public function __invoke(Request $request): JsonResponse
    {
        /** @var Tenant $tenant */
        $tenant = $request->attributes->get(TenantResolverListener::REQUEST_ATTRIBUTE);

        $payload = json_decode($request->getContent(), true);
        $locale = is_array($payload) && isset($payload['defaultLocale']) ? (string) $payload['defaultLocale'] : '';

        try {
            ($this->updateTenantDefaultLocale)($tenant, $locale);
        } catch (ValidationException $exception) {
            return new JsonResponse(ExceptionResponsePayload::for($exception), JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        }

        return new JsonResponse(TenantSettings::fromTenant($tenant)->toArray(), JsonResponse::HTTP_OK);
    }
}
