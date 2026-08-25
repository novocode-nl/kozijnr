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
            'Content-Disposition' => $this->makeContentDisposition($content->originalFilename),
        ]);
    }

    /**
     * KOZ-32 rework: HeaderUtils::makeDisposition() requires its ASCII
     * "filename" fallback to contain only printable ASCII and no '%', and
     * requires BOTH the real filename and the fallback to be free of '/'
     * and '\'. originalFilename is the unsanitized, client-supplied name
     * (only path separators are stripped by Symfony's UploadedFile::getName(),
     * non-ASCII characters and '%' are left intact), so passing it straight
     * through — with no third argument, which makes Symfony fall back to
     * the filename itself as the ASCII fallback — throws an uncaught
     * InvalidArgumentException for names like "café.png", "evil/name.png"
     * or "photo%20mine.png", turning every subsequent GET into a 500.
     *
     * We defensively strip path separators from the real filename (belt and
     * braces on top of UploadedFile's own stripping) and derive a safe,
     * ASCII-only, '%'-free fallback from it. The real (possibly non-ASCII)
     * filename is still preserved via the RFC 6266 filename* parameter that
     * Symfony adds automatically whenever filename !== filenameFallback.
     */
    private function makeContentDisposition(string $originalFilename): string
    {
        $safeFilename = str_replace(['/', '\\'], '_', $originalFilename);

        $asciiFallback = preg_replace('/[^\x20-\x7e]|%/', '_', $safeFilename);
        if ($asciiFallback === '' || trim($asciiFallback, '_') === '') {
            $extension = preg_replace('/[^\x20-\x7e]|%/', '', pathinfo($safeFilename, PATHINFO_EXTENSION));
            $asciiFallback = 'profile-photo' . ($extension !== '' ? '.' . $extension : '');
        }

        return HeaderUtils::makeDisposition(
            HeaderUtils::DISPOSITION_INLINE,
            $safeFilename,
            $asciiFallback,
        );
    }
}
