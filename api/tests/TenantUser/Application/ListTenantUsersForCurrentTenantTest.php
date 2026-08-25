<?php

namespace App\Tests\TenantUser\Application;

use App\TenantUser\Application\ListTenantUsersForCurrentTenant;
use App\TenantUser\Domain\TenantUser;
use App\TenantUser\Domain\TenantUserRepositoryInterface;
use PHPUnit\Framework\TestCase;

final class ListTenantUsersForCurrentTenantTest extends TestCase
{
    public function testListsEveryTenantUserFoundByTheRepositoryAsASummary(): void
    {
        $repository = $this->createMock(TenantUserRepositoryInterface::class);
        $repository->expects(self::once())->method('findAll')->willReturn([
            new TenantUser('beheerder@acme.test', 'hashed', [TenantUser::ROLE_TENANT_ADMIN]),
            new TenantUser('collega@acme.test', 'hashed', [TenantUser::DEFAULT_ROLE]),
        ]);

        $listTenantUsersForCurrentTenant = new ListTenantUsersForCurrentTenant($repository);

        $summaries = $listTenantUsersForCurrentTenant();

        self::assertCount(2, $summaries);
        self::assertSame('beheerder@acme.test', $summaries[0]->email);
        self::assertSame([TenantUser::ROLE_TENANT_ADMIN], $summaries[0]->roles);
        self::assertSame('collega@acme.test', $summaries[1]->email);
    }

    public function testReturnsAnEmptyListWhenTheTenantHasNoUsers(): void
    {
        $repository = $this->createMock(TenantUserRepositoryInterface::class);
        $repository->expects(self::once())->method('findAll')->willReturn([]);

        $listTenantUsersForCurrentTenant = new ListTenantUsersForCurrentTenant($repository);

        self::assertSame([], $listTenantUsersForCurrentTenant());
    }
}
