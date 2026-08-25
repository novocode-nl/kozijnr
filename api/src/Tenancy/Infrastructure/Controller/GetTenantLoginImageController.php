<?php

namespace App\Tenancy\Infrastructure\Controller;

use App\Tenancy\Application\GetTenantLoginImage;
use App\Tenancy\Domain\Tenant;
use App\Tenancy\Infrastructure\TenantResolverListener;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\Request;

/**
 * GET /api/login-image: serves back the current tenant's login-screen
 * image (KOZ-34 kernpunt) — publicly, with no authenticated session,
 * purely from the subdomain the request resolved to
 * (TenantResolverListener runs on every request regardless of auth). 404
 * when no login image has been uploaded, mirroring
 * GetProfilePhotoController.
 *
 * No Content-Disposition header (unlike GetProfilePhotoController): this is
 * always rendered inline via an `<img src>` on the login screen, never
 * downloaded, and — unlike a profile photo's client-supplied original
 * filename — there is no unsanitized filename here at all (the storage key
 * is server-generated), so the non-ASCII-filename Content-Disposition
 * pitfall from KOZ-32's rework doesn't apply.
 *
 * `Cross-Origin-Resource-Policy: cross-origin` is required (KOZ-34 rework,
 * found via manual verification): the frontend's `<img src>` for this tenant
 * hits api.<base> cross-origin from <tenant>.<base> as a plain image
 * request. Browsers send no Origin header for that (CorsListener's
 * Access-Control-* headers never apply — those only ever answer a
 * fetch/XHR-style request), so without this header Chrome's ORB (Opaque
 * Response Blocking) silently blocks the image (net::ERR_BLOCKED_BY_ORB)
 * even though the response is a perfectly valid image/* body.
 */
final class GetTenantLoginImageController
{
    public function __construct(private readonly GetTenantLoginImage $getTenantLoginImage)
    {
    }

    #[Route('/api/login-image', name: 'tenant_login_image_get', methods: ['GET'])]
    public function __invoke(Request $request): Response
    {
        /** @var Tenant $tenant */
        $tenant = $request->attributes->get(TenantResolverListener::REQUEST_ATTRIBUTE);

        $content = ($this->getTenantLoginImage)($tenant);

        if ($content === null) {
            return new Response('', Response::HTTP_NOT_FOUND);
        }

        return new Response($content->contents, Response::HTTP_OK, [
            'Content-Type' => $content->mimeType,
            'Cross-Origin-Resource-Policy' => 'cross-origin',
        ]);
    }
}
