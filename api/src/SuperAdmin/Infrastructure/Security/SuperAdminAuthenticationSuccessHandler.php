<?php

namespace App\SuperAdmin\Infrastructure\Security;

use App\SuperAdmin\Domain\SuperAdmin;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationSuccessHandlerInterface;

/**
 * JSON response for a successful POST /api/admin/login. Deliberately
 * returns only the super admin's own identity (email) — never any tenant
 * data — so the session this establishes cannot be mistaken for carrying
 * tenant context.
 */
final class SuperAdminAuthenticationSuccessHandler implements AuthenticationSuccessHandlerInterface
{
    public function onAuthenticationSuccess(Request $request, TokenInterface $token): Response
    {
        $user = $token->getUser();
        $email = $user instanceof SuperAdmin ? $user->getEmail() : $user->getUserIdentifier();

        return new JsonResponse(['email' => $email], JsonResponse::HTTP_OK);
    }
}
