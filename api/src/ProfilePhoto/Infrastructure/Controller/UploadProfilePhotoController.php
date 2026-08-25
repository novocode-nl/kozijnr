<?php

namespace App\ProfilePhoto\Infrastructure\Controller;

use App\ProfilePhoto\Infrastructure\Http\ProfilePhotoEndpoint;
use App\User\Domain\User;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Uploads a profile photo for the currently authenticated super-admin
 * user (KOZ-32's concrete upload endpoint). Accepts multipart/form-data
 * with a single "photo" field. This controller only resolves the current
 * user the way the session firewall does (#[CurrentUser]) — everything
 * else lives in the shared ProfilePhotoEndpoint.
 */
final class UploadProfilePhotoController
{
    public function __construct(private readonly ProfilePhotoEndpoint $endpoint)
    {
    }

    #[Route('/api/admin/me/profile-photo', name: 'admin_me_profile_photo_upload', methods: ['POST'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function __invoke(Request $request, #[CurrentUser] User $user): JsonResponse
    {
        return $this->endpoint->handleUpload($request, $user->getId(), 'user');
    }
}
