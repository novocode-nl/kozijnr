<?php

namespace App\TenantUser\Infrastructure\Controller;

use App\TenantUser\Domain\TenantUser;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * GET /api/me: minimal protected tenant-user resource proving the bearer
 * token works and is tenant-bound — no other tenant-user business endpoint
 * exists yet. Requires a valid `Authorization: Bearer <token>` and, like
 * /api/login, is only reachable on an actually-resolved tenant subdomain.
 */
final class MeController
{
    public function __construct(private readonly Security $security)
    {
    }

    #[Route('/api/me', name: 'tenant_me', methods: ['GET'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function __invoke(): JsonResponse
    {
        $user = $this->security->getUser();
        $email = $user instanceof TenantUser ? $user->getEmail() : $user?->getUserIdentifier();
        $roles = $user?->getRoles() ?? [];

        return new JsonResponse(['email' => $email, 'roles' => $roles], JsonResponse::HTTP_OK);
    }
}
