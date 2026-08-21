<?php

namespace App\Tenancy\Infrastructure;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * CORS for browser clients living on sibling subdomains of the base domain
 * (admin.<base>, <tenant>.<base>) that call this API cross-origin on
 * api.<base>. Only origins whose host is the base domain itself or ends in
 * ".<base>" are allowed; everything else gets no CORS headers at all, so
 * the browser blocks it.
 *
 * Runs in every environment: production (admin.kozijnr.nl -> api.kozijnr.nl)
 * works exactly like local dev (admin.kozijnr.localhost ->
 * api.kozijnr.localhost behind the nginx proxy). The matching "which
 * tenant/admin context is this browser in?" question is answered from the
 * same Origin header by TenantResolverListener.
 */
final class CorsListener implements EventSubscriberInterface
{
    private const ALLOWED_METHODS = 'GET, POST, PUT, PATCH, DELETE, OPTIONS';

    private const ALLOWED_HEADERS = 'Content-Type, Authorization';

    public function __construct(private readonly string $baseDomain)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            // Before TenantResolverListener (100): a preflight must never
            // reach routing or tenant resolution.
            KernelEvents::REQUEST => ['onKernelRequest', 250],
            KernelEvents::RESPONSE => ['onKernelResponse', 0],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $origin = $request->headers->get('Origin');

        if ($origin === null || $request->getMethod() !== 'OPTIONS' || !$this->isAllowedOrigin($origin)) {
            return;
        }

        // Preflight: the browser only asks permission; answer with the
        // Access-Control-* headers and nothing else.
        $response = new Response('', Response::HTTP_NO_CONTENT);
        $this->addCorsHeaders($response, $origin);
        $event->setResponse($response);
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $origin = $event->getRequest()->headers->get('Origin');

        if ($origin === null || !$this->isAllowedOrigin($origin)) {
            return;
        }

        $this->addCorsHeaders($event->getResponse(), $origin);
    }

    public function isAllowedOrigin(string $origin): bool
    {
        $host = parse_url($origin, PHP_URL_HOST);

        if (!is_string($host)) {
            return false;
        }

        $host = strtolower($host);
        $baseDomain = strtolower($this->baseDomain);

        return $host === $baseDomain || str_ends_with($host, '.' . $baseDomain);
    }

    private function addCorsHeaders(Response $response, string $origin): void
    {
        $headers = $response->headers;
        $headers->set('Access-Control-Allow-Origin', $origin);
        $headers->set('Access-Control-Allow-Credentials', 'true');
        $headers->set('Access-Control-Allow-Methods', self::ALLOWED_METHODS);
        $headers->set('Access-Control-Allow-Headers', self::ALLOWED_HEADERS);
        $headers->set('Vary', 'Origin', false);
    }
}
