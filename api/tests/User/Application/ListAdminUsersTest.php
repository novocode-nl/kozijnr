<?php

namespace App\Tests\User\Application;

use App\User\Application\ListAdminUsers;
use App\User\Domain\Role;
use App\User\Domain\User;
use App\User\Domain\UserRepositoryInterface;
use PHPUnit\Framework\TestCase;

final class ListAdminUsersTest extends TestCase
{
    public function testReturnsAUserSummaryForEveryUser(): void
    {
        $role = new Role('ROLE_SUPER_ADMIN');
        $userA = new User('a@kozijnr.nl', 'hashed', [$role]);
        $userB = new User('b@kozijnr.nl', 'hashed', [$role]);

        $userRepository = $this->createMock(UserRepositoryInterface::class);
        $userRepository->expects(self::once())->method('findAll')->willReturn([$userA, $userB]);

        $listAdminUsers = new ListAdminUsers($userRepository);

        $summaries = $listAdminUsers();

        self::assertCount(2, $summaries);
        self::assertSame('a@kozijnr.nl', $summaries[0]->email);
        self::assertSame(['ROLE_SUPER_ADMIN'], $summaries[0]->roles);
        self::assertSame('b@kozijnr.nl', $summaries[1]->email);
    }

    public function testReturnsAnEmptyArrayWhenThereAreNoUsers(): void
    {
        $userRepository = $this->createMock(UserRepositoryInterface::class);
        $userRepository->method('findAll')->willReturn([]);

        $listAdminUsers = new ListAdminUsers($userRepository);

        self::assertSame([], $listAdminUsers());
    }
}
