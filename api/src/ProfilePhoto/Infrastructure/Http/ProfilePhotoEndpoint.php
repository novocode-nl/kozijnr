<?php

namespace App\ProfilePhoto\Infrastructure\Http;

use App\ProfilePhoto\Application\GetProfilePhoto;
use App\ProfilePhoto\Application\UploadProfilePhoto;
use App\Shared\Domain\Exception\ValidationException;
use App\Shared\Infrastructure\Http\ExceptionResponsePayload;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The HTTP <-> use-case translation the four profile-photo controllers
 * (admin + tenant realm, upload + get) previously each copy-pasted. The
 * controllers keep exactly one job: resolving the authenticated owner the
 * way their own firewall does (#[CurrentUser] User vs Security::getUser()
 * instanceof TenantUser) — everything after "we have an owner id" is
 * identical by construction here.
 *
 * The response never includes the storage key — it is an internal detail
 * of where the file happens to live, not something a client needs.
 */
final class ProfilePhotoEndpoint
{
    public function __construct(
        private readonly UploadProfilePhoto $uploadProfilePhoto,
        private readonly GetProfilePhoto $getProfilePhoto,
        private readonly LoggerInterface $logger,
    ) {
    }

    /** @param string $logContext "user" (admin realm) or "tenant user" — keeps today's log lines intact. */
    public function handleUpload(Request $request, int $ownerId, string $logContext): JsonResponse
    {
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
                $ownerId,
                $file->getClientOriginalName(),
                $mimeType,
                (string) file_get_contents($file->getPathname()),
            );
        } catch (ValidationException $exception) {
            return new JsonResponse(ExceptionResponsePayload::for($exception), JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        } catch (\Throwable $exception) {
            $this->logger->error(sprintf('Profile photo upload failed for %s {userId}: {message}', $logContext), [
                'userId' => $ownerId,
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

    public function handleGet(int $ownerId): Response
    {
        $content = ($this->getProfilePhoto)($ownerId);

        if ($content === null) {
            return new Response('', Response::HTTP_NOT_FOUND);
        }

        return new Response($content->contents, Response::HTTP_OK, [
            'Content-Type' => $content->mimeType,
            // The original filename is client-supplied and unsanitized —
            // build the header via Symfony's HeaderUtils instead of manual
            // string interpolation so a filename containing a double quote
            // can't inject extra header attributes.
            'Content-Disposition' => ProfilePhotoContentDisposition::forOriginalFilename($content->originalFilename),
        ]);
    }
}
