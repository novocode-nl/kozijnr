<?php

namespace App\Tenancy\Application;

use App\Tenancy\Domain\Tenant;

/**
 * KOZ-34: reads back the current tenant's default locale — used publicly
 * (no session) by the login screen (GetTenantLocaleController) so it
 * renders in the tenant's configured language by default, and internally
 * by LoginController to tell the frontend which locale to switch to right
 * after a successful login.
 */
final class GetTenantLocale
{
    public function __invoke(Tenant $tenant): string
    {
        return $tenant->getDefaultLocale();
    }
}
