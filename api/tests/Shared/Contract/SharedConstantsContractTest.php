<?php

namespace App\Tests\Shared\Contract;

use App\Tenancy\Domain\Subdomain;
use App\Tenancy\Domain\Tenant;
use App\TenantUser\Domain\TenantUser;
use App\TenantUser\Infrastructure\Security\TenantApiTokenCookie;
use PHPUnit\Framework\TestCase;

/**
 * Guards the constants the frontend mirrors (see the counterpart test in
 * web/lib/contract/shared-constants.test.ts and scripts/check-contracts.mjs,
 * which keeps the JSON copies identical): whoever changes one of these
 * constants must consciously update the contract file — and thereby the
 * other side — instead of silently drifting apart.
 */
final class SharedConstantsContractTest extends TestCase
{
    /** @return array<string, mixed> */
    private static function contract(): array
    {
        $json = file_get_contents(\dirname(__DIR__, 3) . '/config/contract/shared-constants.json');
        self::assertIsString($json);

        return json_decode($json, true, flags: \JSON_THROW_ON_ERROR);
    }

    public function testLocalesMatchContract(): void
    {
        $contract = self::contract();

        self::assertSame($contract['locales']['supported'], Tenant::SUPPORTED_LOCALES);
        self::assertSame($contract['locales']['default'], Tenant::DEFAULT_LOCALE);
    }

    public function testTenantUserRolesMatchContract(): void
    {
        $contract = self::contract();

        self::assertSame($contract['tenantUserRoles']['admin'], TenantUser::ROLE_TENANT_ADMIN);
        self::assertSame($contract['tenantUserRoles']['default'], TenantUser::DEFAULT_ROLE);
    }

    public function testTokenCookieNameMatchesContract(): void
    {
        self::assertSame(self::contract()['cookies']['tenantApiToken'], TenantApiTokenCookie::NAME);
    }

    public function testReservedSubdomainsMatchContract(): void
    {
        $contract = self::contract();

        self::assertSame($contract['reservedSubdomains']['admin'], Subdomain::RESERVED_ADMIN);
        self::assertSame($contract['reservedSubdomains']['api'], Subdomain::RESERVED_API);
    }
}
