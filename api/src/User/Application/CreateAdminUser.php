<?php

namespace App\User\Application;

use App\Shared\Domain\EmailAddress;
use App\Shared\Domain\Security\GeneratedPassword;

/**
 * Admin-UI counterpart to `bin/console super-admin:create` (KOZ-30): lets a
 * super admin create another super-admin account from the admin UI, without
 * terminal access. Delegates to CreateSuperAdmin for the actual account
 * creation (email-uniqueness check, ROLE_SUPER_ADMIN lookup, password
 * hashing) and only adds what the CLI command's interactive prompt used to
 * provide: email-format validation and a generated password.
 *
 * Every admin user created this way gets ROLE_SUPER_ADMIN, same as the CLI
 * command — role/permission selection in the UI is out of scope for KOZ-30.
 *
 * Password generation mirrors
 * App\Tenancy\Application\ProvisionTenantWithAdmin's tenant-admin password:
 * bin2hex(random_bytes(12)), for the same one-time-credentials-dialog UX.
 */
final class CreateAdminUser
{
    public function __construct(private readonly CreateSuperAdmin $createSuperAdmin)
    {
    }

    public function __invoke(string $email): CreatedAdminUser
    {
        $trimmedEmail = EmailAddress::validated(
            $email,
            'Admin user email must be a valid email address.',
            'users.error.emailInvalid',
        );

        $password = GeneratedPassword::generate();

        $user = ($this->createSuperAdmin)($trimmedEmail, $password);

        return new CreatedAdminUser($user, $password);
    }
}
