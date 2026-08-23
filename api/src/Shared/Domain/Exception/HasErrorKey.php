<?php

namespace App\Shared\Domain\Exception;

/**
 * Implemented by Domain exceptions that represent a user-facing error the
 * frontend should show translated. Rather than the backend translating
 * anything itself, the exception exposes a stable, machine-readable error
 * key (e.g. "form.error.tenantAlreadyExistsSubdomain") plus any
 * interpolation params — the frontend's own i18n catalog (it already has
 * one, see KOZ-29) looks the key up and renders it in whichever language
 * the user has selected.
 *
 * Kept dependency-free on purpose: the Domain layer must not know about
 * HTTP or any translation mechanism. Presentation-layer controllers read
 * these keys off a caught exception and put them straight into the JSON
 * error response (see App\Shared\Infrastructure\Http\ExceptionResponsePayload).
 *
 * getMessage() on the exception itself keeps returning a plain English
 * message — used for logs and as the `message` fallback for any API
 * consumer that doesn't do its own key-based translation.
 */
interface HasErrorKey
{
    public function getErrorKey(): string;

    /** @return array<string, scalar> */
    public function getErrorKeyParams(): array;
}
