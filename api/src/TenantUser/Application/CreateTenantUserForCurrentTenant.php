<?php

namespace App\TenantUser\Application;

use App\Shared\Domain\EmailAddress;
use App\Shared\Domain\Exception\ValidationException;
use App\Shared\Domain\Security\GeneratedPassword;
use App\TenantUser\Domain\TenantUser;

/**
 * Creates an additional tenant-user account inside whichever tenant schema
 * is *already* active on the current Doctrine connection (KOZ-31 rework).
 *
 * Two callers, two different ways of getting there:
 *  - the tenant-own self-service "Gebruiker toevoegen" flow
 *    (App\TenantUser\Infrastructure\Controller\CreateOwnTenantUserController):
 *    search_path is already pointed at the logged-in tenant-admin's own
 *    tenant by TenantResolverListener, purely from the request's Host
 *    header — this class never receives, and never needs, a subdomain, so
 *    a tenant-admin has no way to (accidentally or otherwise) create a user
 *    in a tenant they don't belong to.
 *  - the admin "Gebruikers" tab
 *    (App\TenantUser\Application\CreateTenantUserForTenant), which
 *    switches search_path itself first (an operator, acting on a
 *    client-supplied subdomain, on behalf of a tenant they don't belong
 *    to), then delegates here for the actual validation + write.
 *
 * The caller picks a role from the two that exist on TenantUser
 * (ROLE_TENANT_ADMIN / the default ROLE_TENANT_USER). A generated password
 * is returned once, alongside the created user, the same one-time-
 * credentials pattern ProvisionTenantWithAdmin/CreateTenantController
 * already use.
 */
final class CreateTenantUserForCurrentTenant
{
    /** @var list<string> */
    private const ALLOWED_ROLES = [TenantUser::ROLE_TENANT_ADMIN, TenantUser::DEFAULT_ROLE];

    public function __construct(private readonly CreateTenantUser $createTenantUser)
    {
    }

    public function __invoke(string $email, string $role): CreatedTenantUserForTenant
    {
        $email = EmailAddress::validated(
            $email,
            'Tenant-user email must be a valid email address.',
            'tenants.error.userEmailInvalid',
        );

        if (!in_array($role, self::ALLOWED_ROLES, true)) {
            throw ValidationException::create(
                sprintf('Invalid tenant-user role "%s".', $role),
                'tenants.error.userRoleInvalid',
            );
        }

        $password = GeneratedPassword::generate();
        $tenantUser = ($this->createTenantUser)($email, $password, [$role]);

        return new CreatedTenantUserForTenant($tenantUser->getEmail(), $tenantUser->getRoles(), $password);
    }
}
