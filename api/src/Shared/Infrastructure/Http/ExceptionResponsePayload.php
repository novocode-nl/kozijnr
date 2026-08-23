<?php

namespace App\Shared\Infrastructure\Http;

use App\Shared\Domain\Exception\HasErrorKey;

/**
 * Builds the JSON body a controller returns for a caught exception:
 * always an English `message` (fallback for logs / any consumer not doing
 * key-based translation), plus an `errorKey` (and `errorKeyParams`, when
 * there are any) when the exception is translatable — see HasErrorKey.
 *
 * The frontend looks `errorKey` up in its own i18n catalog and renders it
 * in the active language; `message` is not shown to the user when a key is
 * present.
 */
final class ExceptionResponsePayload
{
    /** @return array{message: string, errorKey?: string, errorKeyParams?: array<string, scalar>} */
    public static function for(\Throwable $exception): array
    {
        $payload = ['message' => $exception->getMessage()];

        if ($exception instanceof HasErrorKey) {
            $payload['errorKey'] = $exception->getErrorKey();
            $params = $exception->getErrorKeyParams();
            if ($params !== []) {
                $payload['errorKeyParams'] = $params;
            }
        }

        return $payload;
    }

    /**
     * For the generic 500-style fallback branches that aren't built from a
     * caught exception's own message (e.g. "Failed to create tenant."),
     * paired with a fixed error key for the same reason.
     *
     * @return array{message: string, errorKey: string}
     */
    public static function withKey(string $message, string $errorKey): array
    {
        return ['message' => $message, 'errorKey' => $errorKey];
    }
}
