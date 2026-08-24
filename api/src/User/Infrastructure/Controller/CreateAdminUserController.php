<?php

namespace App\User\Infrastructure\Controller;

use App\Shared\Infrastructure\Http\ExceptionResponsePayload;
use App\User\Application\CreateAdminUser;
use App\User\Application\UserSummary;
use App\User\Domain\Exception\UserAlreadyExistsException;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Admin-UI counterpart to `bin/console super-admin:create` (KOZ-30): lets a
 * super admin create another admin-user account without terminal access.
 * Route sits under /api/admin, guarded by AdminRouteGuardListener
 * (unreachable from a tenant subdomain).
 *
 * Delegates to CreateAdminUser, which validates the email, creates the
 * account with ROLE_SUPER_ADMIN and a generated password. The credentials
 * are included in the response — this is the only time the plain-text
 * password is ever available, since only its hash is persisted.
 *
 * Authorization is permission-based (`user:create`, checked via
 * PermissionVoter), same pattern as CreateTenantController.
 */
final class CreateAdminUserController
{
    public function __construct(
        private readonly CreateAdminUser $createAdminUser,
        private readonly LoggerInterface $logger,
    ) {
    }

    #[Route('/api/admin/users', name: 'admin_users_create', methods: ['POST'])]
    #[IsGranted('user:create')]
    public function __invoke(Request $request): JsonResponse
    {
        $payload = json_decode($request->getContent(), true);
        $email = is_array($payload) && isset($payload['email']) ? (string) $payload['email'] : '';

        try {
            $result = ($this->createAdminUser)($email);
        } catch (\InvalidArgumentException|UserAlreadyExistsException $exception) {
            return new JsonResponse(ExceptionResponsePayload::for($exception), JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        } catch (\Throwable $exception) {
            // Report cleanly rather than leaking a stack trace, but log the real cause.
            $this->logger->error('Admin user creation failed for "{email}": {message}', [
                'email' => $email,
                'message' => $exception->getMessage(),
                'exception' => $exception,
            ]);

            return new JsonResponse(
                ExceptionResponsePayload::withKey('Failed to create admin user.', 'users.error.createFailed'),
                JsonResponse::HTTP_INTERNAL_SERVER_ERROR,
            );
        }

        return new JsonResponse([
            ...UserSummary::fromUser($result->user)->toArray(),
            'password' => $result->password,
        ], JsonResponse::HTTP_CREATED);
    }
}
