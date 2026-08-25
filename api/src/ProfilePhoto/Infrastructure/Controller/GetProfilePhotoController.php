<?php

namespace App\ProfilePhoto\Infrastructure\Controller;

use App\ProfilePhoto\Application\GetProfilePhoto;
use App\User\Domain\User;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Serves back the currently authenticated super-admin user's own profile
 * photo (KOZ-32 DoD: "... terug op te vragen"). 404 when none has been
 * uploaded yet.
 */
final class GetProfilePhotoController
{
    public function __construct(
        private readonly GetProfilePhoto $getProfilePhoto,
    ) {
    }

    #[Route('/api/admin/me/profile-photo', name: 'admin_me_profile_photo_get', methods: ['GET'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function __invoke(#[CurrentUser] User $user): Response
    {
        $content = ($this->getProfilePhoto)($user->getId());

        if ($content === null) {
            return new Response('', Response::HTTP_NOT_FOUND);
        }

        return new Response($content->contents, Response::HTTP_OK, [
            'Content-Type' => $content->mimeType,
            // The original filename is client-supplied and unsanitized —
            // build the header via Symfony's HeaderUtils instead of manual
            // string interpolation so a filename containing a double quote
            // can't inject extra header attributes.
            'Content-Disposition' => HeaderUtils::makeDisposition(
                HeaderUtils::DISPOSITION_INLINE,
                $content->originalFilename,
            ),
        ]);
    }
}
