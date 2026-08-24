<?php

namespace App\ProfilePhoto\Infrastructure\Controller;

use App\ProfilePhoto\Application\UploadProfilePhoto;
use App\Shared\Domain\Exception\ValidationException;
use App\Shared\Infrastructure\Http\ExceptionResponsePayload;
use App\User\Domain\User;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Uploads a profile photo for the currently authenticated super-admin
 * user (KOZ-32's concrete upload endpoint). Accepts multipart/form-data
 * with a single "photo" field. All validation (file type, size) happens
 * in UploadProfilePhoto, not here and not in the storage adapter — this
 * controller only translates HTTP <-> the Application-layer command.
 *
 * The response never includes the storage key — it is an internal detail
 * of where the file happens to live, not something a client needs.
 */
final class UploadProfilePhotoController
{
    public function __construct(
        private readonly UploadProfilePhoto $uploadProfilePhoto,
        private readonly LoggerInterface $logger,
    ) {
    }

    #[Route('/api/admin/me/profile-photo', name: 'admin_me_profile_photo_upload', methods: ['POST'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function __invoke(Request $request, #[CurrentUser] User $user): JsonResponse
    {
        $file = $request->files->get('photo');

        if (!$file instanceof UploadedFile || !$file->isValid()) {
            return new JsonResponse(
                ExceptionResponsePayload::withKey('No valid photo file was uploaded.', 'profilePhoto.error.missingFile'),
                JsonResponse::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        try {
            $photo = ($this->uploadProfilePhoto)(
                $user->getId(),
                $file->getClientOriginalName(),
                $file->getMimeType() ?? $file->getClientMimeType(),
                (string) file_get_contents($file->getPathname()),
            );
        } catch (ValidationException $exception) {
            return new JsonResponse(ExceptionResponsePayload::for($exception), JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        } catch (\Throwable $exception) {
            $this->logger->error('Profile photo upload failed for user {userId}: {message}', [
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
