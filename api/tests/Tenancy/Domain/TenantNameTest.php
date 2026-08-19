<?php

namespace App\Tests\Tenancy\Domain;

use App\Tenancy\Domain\Exception\InvalidTenantNameException;
use App\Tenancy\Domain\TenantName;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * TenantName is the single point where a freely-typed tenant name (as given
 * to `tenant:provision <name>`) is validated against a strict whitelist and
 * turned into both the subdomain and the Postgres schema name derived from
 * it. Schema/subdomain names are interpolated into raw SQL identifiers
 * elsewhere (CREATE SCHEMA, SET search_path), so this whitelist is the only
 * thing standing between a free-text name and SQL-schema injection.
 */
final class TenantNameTest extends TestCase
{
    public function testAcceptsASimpleLowercaseAlphanumericName(): void
    {
        $name = new TenantName('acme');

        self::assertSame('acme', $name->asSubdomain());
        self::assertSame('tenant_acme', $name->asSchemaName());
    }

    public function testAcceptsHyphenSeparatedSegmentsAndTurnsThemIntoUnderscoresForTheSchemaName(): void
    {
        $name = new TenantName('acme-bv');

        self::assertSame('acme-bv', $name->asSubdomain());
        self::assertSame('tenant_acme_bv', $name->asSchemaName());
    }

    public function testAcceptsDigitsWithinASegment(): void
    {
        $name = new TenantName('acme2');

        self::assertSame('acme2', $name->asSubdomain());
    }

    /** @return iterable<string, array{0: string}> */
    public static function invalidNameProvider(): iterable
    {
        yield 'empty string' => [''];
        yield 'whitespace only' => ['   '];
        yield 'uppercase letters' => ['Acme'];
        yield 'leading hyphen' => ['-acme'];
        yield 'trailing hyphen' => ['acme-'];
        yield 'double hyphen' => ['acme--bv'];
        yield 'underscore' => ['acme_bv'];
        yield 'space' => ['acme bv'];
        yield 'dot' => ['acme.bv'];
        yield 'single quote (SQL injection attempt)' => ["acme'; DROP SCHEMA public CASCADE; --"];
        yield 'double quote (identifier-quoting escape attempt)' => ['acme"; DROP TABLE tenants; --'];
        yield 'semicolon' => ['acme;drop'];
        yield 'sql comment marker' => ['acme--comment'];
        yield 'backslash' => ['acme\\bv'];
        yield 'null byte' => ["acme\0bv"];
        yield 'slash' => ['acme/bv'];
        yield 'too long (56 chars)' => [str_repeat('a', 56)];
        yield 'reserved "admin" subdomain' => ['admin'];
    }

    #[DataProvider('invalidNameProvider')]
    public function testRejectsInvalidNames(string $invalidName): void
    {
        $this->expectException(InvalidTenantNameException::class);

        new TenantName($invalidName);
    }

    public function testAcceptsTheMaximumAllowedLength(): void
    {
        $name = new TenantName(str_repeat('a', 55));

        self::assertSame(55, strlen($name->asSubdomain()));
    }

    public function testToStringReturnsTheSubdomain(): void
    {
        $name = new TenantName('acme');

        self::assertSame('acme', (string) $name);
    }
}
