<?php

namespace App\ProfilePhoto\Infrastructure\Controller;

use App\ProfilePhoto\Application\UploadProfilePhoto;
use App\Shared\Domain\Exception\ValidationException;
use App\Shared\Infrastructure\Http\ExceptionResponsePayload;
use App\TenantUser\Domain\TenantUser;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * KOZ-33: uploads a profile photo for the currently authenticated
 * tenant-user — the tenant-realm counterpart of
 * UploadProfilePhotoController. Reuses the same owner-agnostic
 * App\ProfilePhoto\Application\UploadProfilePhoto command handler (and
 * therefore the same mime-type allowlist / 5MB limit / replace-on-reupload
 * behaviour) with the tenant user's own id as the owner — see
 * migrations-tenant/Version20260825090000 for why a `profile_photos` table
 * now also exists inside every tenant schema.
 *
 * Uses Security::getUser() rather than #[CurrentUser] to match every other
 * TenantUser controller (e.g. MeController) — the stateless access_token
 * firewall resolves the user the same way there.
 */
final class UploadTenantProfilePhotoController
{
    public function __construct(
        private readonly UploadProfilePhoto $uploadProfilePhoto,
        private readonly Security $security,
        private readonly LoggerInterface $logger,
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

        $file = $request->files->get('photo');

        if (!$file instanceof UploadedFile || !$file->isValid()) {
            return new JsonResponse(
                ExceptionResponsePayload::withKey('No valid photo file was uploaded.', 'profilePhoto.error.missingFile'),
                JsonResponse::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        // finfo inspects the actual file contents; the client-sent
        // Content-Type header (getClientMimeType()) is attacker-controlled
        // and must never be trusted as a fallback. When finfo can't
        // classify the content, reject outright rather than falling back
        // to what the client claims.
        $mimeType = $file->getMimeType();

        if ($mimeType === null) {
            return new JsonResponse(
                ExceptionResponsePayload::withKey('Could not determine the profile photo mime type.', 'profilePhoto.error.unsupportedMimeType'),
                JsonResponse::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        try {
            $photo = ($this->uploadProfilePhoto)(
                $user->getId(),
                $file->getClientOriginalName(),
                $mimeType,
                (string) file_get_contents($file->getPathname()),
            );
        } catch (ValidationException $exception) {
            return new JsonResponse(ExceptionResponsePayload::for($exception), JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        } catch (\Throwable $exception) {
            $this->logger->error('Profile photo upload failed for tenant user {userId}: {message}', [
                'userId' => $user->getId(),
                'message' => $exception->getMessage(),
                'exception' => $exception,
            ]);

            return new JsonResponse(
                ExceptionResponsePayload::withKey('Failed to upload profile photo.', 'profilePhoto.error.uploadFailed'),
                JsonResponse::HTTP_INTERNAL_SERVER_ERROR,
            );
        }

        return new JsonResponse([
            'id' => $photo->getId(),
            'mimeType' => $photo->getMimeType(),
            'sizeInBytes' => $photo->getSizeInBytes(),
            'originalFilename' => $photo->getOriginalFilename(),
            'uploadedAt' => $photo->getUploadedAt()->format(\DATE_ATOM),
        ], JsonResponse::HTTP_CREATED);
    }
}
