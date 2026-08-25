<?php

namespace App\Shared\Domain\Security;

/**
 * The one-time generated password handed out exactly once in a create
 * response (KOZ-27/30/31 pattern). One definition of its shape/entropy
 * instead of three inlined bin2hex(random_bytes(12)) call sites.
 */
final class GeneratedPassword
{
    public static function generate(): string
    {
        return bin2hex(random_bytes(12));
    }
}
