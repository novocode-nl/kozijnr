<?php

namespace App\Tests\Shared\Domain;

use App\Shared\Domain\EmailAddress;
use App\Shared\Domain\Exception\ValidationException;
use PHPUnit\Framework\TestCase;

final class EmailAddressTest extends TestCase
{
    public function testTrimsAndReturnsAValidEmail(): void
    {
        self::assertSame('a@b.nl', EmailAddress::validated('  a@b.nl ', 'Invalid.', 'x.error.emailInvalid'));
    }

    public function testThrowsWithTheCallersMessageAndKeyForAnInvalidEmail(): void
    {
        try {
            EmailAddress::validated('nope', 'Admin user email must be a valid email address.', 'users.error.emailInvalid');
            self::fail('Expected ValidationException');
        } catch (ValidationException $exception) {
            self::assertSame('Admin user email must be a valid email address.', $exception->getMessage());
            self::assertSame('users.error.emailInvalid', $exception->getErrorKey());
        }
    }

    public function testThrowsForAnEmptyOrWhitespaceEmail(): void
    {
        $this->expectException(ValidationException::class);

        EmailAddress::validated('   ', 'Invalid.', 'x.error.emailInvalid');
    }
}
