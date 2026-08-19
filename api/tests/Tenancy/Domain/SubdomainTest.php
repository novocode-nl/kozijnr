<?php

namespace App\Tests\Tenancy\Domain;

use App\Tenancy\Domain\Subdomain;
use PHPUnit\Framework\TestCase;

final class SubdomainTest extends TestCase
{
    public function testMainDomainHasNoSubdomain(): void
    {
        self::assertNull(Subdomain::extractFrom('localhost', 'localhost'));
    }

    public function testHostWithSubdomainReturnsTheSubdomainLabel(): void
    {
        self::assertSame('tenant-a', Subdomain::extractFrom('tenant-a.localhost', 'localhost'));
    }

    public function testProductionBaseDomainHasNoSubdomain(): void
    {
        self::assertNull(Subdomain::extractFrom('kozijnr.nl', 'kozijnr.nl'));
    }

    public function testProductionHostWithSubdomainReturnsTheSubdomainLabel(): void
    {
        self::assertSame('acme', Subdomain::extractFrom('acme.kozijnr.nl', 'kozijnr.nl'));
    }

    public function testUnrelatedHostHasNoSubdomain(): void
    {
        self::assertNull(Subdomain::extractFrom('unrelated-host.example.com', 'localhost'));
    }

    public function testMatchingIsCaseInsensitive(): void
    {
        self::assertSame('tenant-a', Subdomain::extractFrom('Tenant-A.LOCALHOST', 'localhost'));
    }
}
