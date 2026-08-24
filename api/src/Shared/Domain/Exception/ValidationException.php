<?php

namespace App\Shared\Domain\Exception;

/**
 * Generic error-key-carrying validation failure for simple, single-field
 * Domain invariants (required field, max length, invalid format, ...) that
 * don't otherwise warrant their own dedicated exception class per context.
 *
 * Domain invariants that need richer behavior (multiple named factory
 * methods, extra context) still get their own typed exception under the
 * context's own Domain/Exception/ namespace (see e.g.
 * App\Tenancy\Domain\Exception\TenantAlreadyExistsException) — this class
 * exists so straightforward "field X is invalid" checks don't each need a
 * bespoke class.
 */
final class ValidationException extends \InvalidArgumentException implements HasErrorKey
{
    /** @param array<string, scalar> $errorKeyParams */
    private function __construct(
        string $message,
        private readonly string $errorKey,
        private readonly array $errorKeyParams,
    ) {
        parent::__construct($message);
    }

    /** @param array<string, scalar> $errorKeyParams */
    public static function create(string $englishMessage, string $errorKey, array $errorKeyParams = []): self
    {
        return new self($englishMessage, $errorKey, $errorKeyParams);
    }

    public function getErrorKey(): string
    {
        return $this->errorKey;
    }

    /** @return array<string, scalar> */
    public function getErrorKeyParams(): array
    {
        return $this->errorKeyParams;
    }
}
