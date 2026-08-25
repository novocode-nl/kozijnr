<?php

namespace App\Tenancy\Infrastructure\Controller;

use App\Shared\Domain\Exception\ValidationException;
use App\Shared\Infrastructure\Http\ExceptionResponsePayload;
use App\Tenancy\Application\TenantSettings;
use App\Tenancy\Application\UploadTenantLoginImage;
use App\Tenancy\Domain\Tenant;
use App\Tenancy\Infrastructure\TenantResolverListener;
use App\TenantUser\Domain\TenantUser;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * POST /api/settings/login-image: uploads the current tenant's
 * login-screen image (KOZ-34). Accepts multipart/form-data with a single
 * "image" field — deliberately mirrors
 * App\ProfilePhoto\Infrastructure\Controller\UploadProfilePhotoController's
 * shape (finfo-based mime-type detection, never trusting the client's
 * Content-Type header; all validation happens in the Application-layer
 * command, not here). Same authorization/tenant-resolution shape as
 * GetTenantSettingsController.
 */
final class UploadTenantLoginImageController
{
    public function __construct(
        private readonly UploadTenantLoginImage $uploadTenantLoginImage,
        private readonly LoggerInterface $logger,
    ) {
    }

    #[Route('/api/settings/login-image', name: 'tenant_settings_upload_login_image', methods: ['POST'])]
    #[IsGranted(TenantUser::ROLE_TENANT_ADMIN)]
    public function __invoke(Request $request): JsonResponse
    {
        /** @var Tenant $tenant */
        $tenant = $request->attributes->get(TenantResolverListener::REQUEST_ATTRIBUTE);

        $file = $request->files->get('image');

        if (!$file instanceof UploadedFile || !$file->isValid()) {
            return new JsonResponse(
                ExceptionResponsePayload::withKey('No valid login image file was uploaded.', 'tenantSettings.error.missingFile'),
                JsonResponse::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        $mimeType = $file->getMimeType();

        if ($mimeType === null) {
            return new JsonResponse(
                ExceptionResponsePayload::withKey('Could not determine the login image mime type.', 'tenantSettings.error.unsupportedMimeType'),
                JsonResponse::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        try {
            ($this->uploadTenantLoginImage)(
                $tenant,
                $file->getClientOriginalName(),
                $mimeType,
                (string) file_get_contents($file->getPathname()),
            );
        } catch (ValidationException $exception) {
            return new JsonResponse(ExceptionResponsePayload::for($exception), JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        } catch (\Throwable $exception) {
            $this->logger->error('Tenant login image upload failed for tenant {subdomain}: {message}', [
                'subdomain' => $tenant->getSubdomain(),
                'message' => $exception->getMessage(),
                'exception' => $exception,
            ]);

            return new JsonResponse(
                ExceptionResponsePayload::withKey('Failed to upload login image.', 'tenantSettings.error.uploadFailed'),
                JsonResponse::HTTP_INTERNAL_SERVER_ERROR,
            );
        }

        return new JsonResponse(TenantSettings::fromTenant($tenant)->toArray(), JsonResponse::HTTP_CREATED);
    }
}
