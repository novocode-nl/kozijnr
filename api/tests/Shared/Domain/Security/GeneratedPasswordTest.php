<?php

namespace App\Tests\Shared\Domain\Security;

use App\Shared\Domain\Security\GeneratedPassword;
use PHPUnit\Framework\TestCase;

final class GeneratedPasswordTest extends TestCase
{
    public function testGeneratesTwentyFourLowercaseHexCharacters(): void
    {
        $password = GeneratedPassword::generate();

        self::assertMatchesRegularExpression('/^[0-9a-f]{24}$/', $password);
    }

    public function testGeneratesADifferentPasswordEachTime(): void
    {
        self::assertNotSame(GeneratedPassword::generate(), GeneratedPassword::generate());
    }
}
