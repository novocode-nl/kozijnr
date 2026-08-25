<?php

namespace App\ProfilePhoto\Infrastructure\Controller;

use App\ProfilePhoto\Infrastructure\Http\ProfilePhotoEndpoint;
use App\Shared\Infrastructure\Http\ExceptionResponsePayload;
use App\TenantUser\Domain\TenantUser;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * KOZ-33: uploads a profile photo for the currently authenticated
 * tenant-user — the tenant-realm counterpart of
 * UploadProfilePhotoController, delegating to the same shared
 * ProfilePhotoEndpoint (and therefore the same mime-type allowlist / 5MB
 * limit / replace-on-reupload behaviour) with the tenant user's own id as
 * the owner — see migrations-tenant/Version20260825090000 for why a
 * `profile_photos` table also exists inside every tenant schema.
 *
 * Uses Security::getUser() rather than #[CurrentUser] to match every other
 * TenantUser controller (e.g. MeController) — the stateless access_token
 * firewall resolves the user the same way there.
 */
final class UploadTenantProfilePhotoController
{
    public function __construct(
        private readonly ProfilePhotoEndpoint $endpoint,
        private readonly Security $security,
    ) {
    }

    #[Route('/api/me/profile-photo', name: 'tenant_me_profile_photo_upload', methods: ['POST'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function __invoke(Request $request): JsonResponse
    {
        $user = $this->security->getUser();
        if (!$user instanceof TenantUser) {
            return new JsonResponse(
                ExceptionResponsePayload::withKey('Not authenticated as a tenant user.', 'profilePhoto.error.uploadFailed'),
                JsonResponse::HTTP_UNAUTHORIZED,
            );
        }

        return $this->endpoint->handleUpload($request, $user->getId(), 'tenant user');
    }
}
