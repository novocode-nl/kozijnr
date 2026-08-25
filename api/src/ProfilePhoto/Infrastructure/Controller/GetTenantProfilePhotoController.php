<?php

namespace App\ProfilePhoto\Infrastructure\Controller;

use App\ProfilePhoto\Application\GetProfilePhoto;
use App\ProfilePhoto\Infrastructure\Http\ProfilePhotoContentDisposition;
use App\TenantUser\Domain\TenantUser;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * KOZ-33: serves back the currently authenticated tenant-user's own
 * profile photo — the tenant-realm counterpart of GetProfilePhotoController.
 * 404 when none has been uploaded yet. Authorization is structural: the
 * owner id always comes from the authenticated tenant user's own id, never
 * from client input, so there is no way to request another user's photo.
 */
final class GetTenantProfilePhotoController
{
    public function __construct(
        private readonly GetProfilePhoto $getProfilePhoto,
        private readonly Security $security,
    ) {
    }

    #[Route('/api/me/profile-photo', name: 'tenant_me_profile_photo_get', methods: ['GET'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function __invoke(): Response
    {
        $user = $this->security->getUser();
        if (!$user instanceof TenantUser) {
            return new Response('', Response::HTTP_UNAUTHORIZED);
        }

        $content = ($this->getProfilePhoto)($user->getId());

        if ($content === null) {
            return new Response('', Response::HTTP_NOT_FOUND);
        }

        return new Response($content->contents, Response::HTTP_OK, [
            'Content-Type' => $content->mimeType,
            'Content-Disposition' => ProfilePhotoContentDisposition::forOriginalFilename($content->originalFilename),
        ]);
    }
}
