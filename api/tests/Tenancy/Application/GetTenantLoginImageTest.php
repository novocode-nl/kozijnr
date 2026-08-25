<?php

namespace App\Tests\Tenancy\Application;

use App\Shared\Domain\Storage\FileStorageInterface;
use App\Tenancy\Application\GetTenantLoginImage;
use App\Tenancy\Application\TenantLoginImageContent;
use App\Tenancy\Domain\Tenant;
use PHPUnit\Framework\TestCase;

/**
 * Query handler (CQRS — zero side effects): reads the current tenant's
 * login image content via the storage port. Deliberately takes the
 * already-resolved `Tenant` (not a subdomain) — see
 * GetTenantLoginImageController, which resolves it from
 * TenantResolverListener's request attribute, purely from the Host header,
 * *before* any authentication — this must work with no session at all.
 */
final class GetTenantLoginImageTest extends TestCase
{
    public function testReturnsNullWhenTheTenantHasNoLoginImage(): void
    {
        $tenant = new Tenant('Acme B.V.', 'acme', 'tenant_acme');

        $storage = $this->createMock(FileStorageInterface::class);
        $storage->expects(self::never())->method('read');

        $handler = new GetTenantLoginImage($storage);

        self::assertNull($handler($tenant));
    }

    public function testReturnsContentAndMimeTypeWhenALoginImageExists(): void
    {
        $tenant = new Tenant(
            'Acme B.V.',
            'acme',
            'tenant_acme',
            loginImageStorageKey: 'tenant-login-images/7/abc.png',
        );

        $storage = $this->createMock(FileStorageInterface::class);
        $storage->method('read')->with('tenant-login-images/7/abc.png')->willReturn('binary');

        $handler = new GetTenantLoginImage($storage);

        $result = $handler($tenant);

        self::assertInstanceOf(TenantLoginImageContent::class, $result);
        self::assertSame('binary', $result->contents);
        self::assertSame('image/png', $result->mimeType);
    }

    public function testDerivesTheMimeTypeFromTheStorageKeysExtension(): void
    {
        $tenant = new Tenant(
            'Acme B.V.',
            'acme',
            'tenant_acme',
            loginImageStorageKey: 'tenant-login-images/7/abc.webp',
        );

        $storage = $this->createMock(FileStorageInterface::class);
        $storage->method('read')->willReturn('binary');

        $handler = new GetTenantLoginImage($storage);

        self::assertSame('image/webp', $handler($tenant)->mimeType);
    }
}
