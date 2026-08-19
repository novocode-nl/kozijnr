<?php

namespace App\Tests\User\Infrastructure\Security;

use App\User\Domain\Permission;
use App\User\Domain\Role;
use App\User\Domain\User;
use App\User\Infrastructure\Security\PermissionVoter;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;

final class PermissionVoterTest extends TestCase
{
    public function testGrantsAccessWhenTheUserHasThePermission(): void
    {
        $role = new Role('ROLE_SUPER_ADMIN');
        $role->addPermission(new Permission('tenant:list'));
        $user = new User('admin@kozijnr.nl', 'hashed', [$role]);

        $voter = new PermissionVoter();

        self::assertSame(
            VoterInterface::ACCESS_GRANTED,
            $voter->vote($this->tokenFor($user), null, ['tenant:list']),
        );
    }

    public function testDeniesAccessWhenTheUserLacksThePermission(): void
    {
        $role = new Role('ROLE_SUPER_ADMIN');
        $role->addPermission(new Permission('tenant:list'));
        $user = new User('admin@kozijnr.nl', 'hashed', [$role]);

        $voter = new PermissionVoter();

        self::assertSame(
            VoterInterface::ACCESS_DENIED,
            $voter->vote($this->tokenFor($user), null, ['tenant:create']),
        );
    }

    public function testAbstainsForAttributesThatAreNotPermissionStrings(): void
    {
        $user = new User('admin@kozijnr.nl', 'hashed', [new Role('ROLE_SUPER_ADMIN')]);

        $voter = new PermissionVoter();

        self::assertSame(
            VoterInterface::ACCESS_ABSTAIN,
            $voter->vote($this->tokenFor($user), null, ['ROLE_SUPER_ADMIN']),
        );
    }

    public function testDeniesAccessWhenThereIsNoAuthenticatedUser(): void
    {
        $token = $this->createStub(TokenInterface::class);
        $token->method('getUser')->willReturn(null);

        $voter = new PermissionVoter();

        self::assertSame(
            VoterInterface::ACCESS_DENIED,
            $voter->vote($token, null, ['tenant:list']),
        );
    }

    private function tokenFor(User $user): TokenInterface
    {
        $token = $this->createStub(TokenInterface::class);
        $token->method('getUser')->willReturn($user);

        return $token;
    }
}
