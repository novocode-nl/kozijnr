<?php

namespace App\Tests\Tenancy\Infrastructure;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Browser clients (admin.<base>, <tenant>.<base>) call this API cross-origin
 * on api.<base>; CorsListener must let exactly those origins through.
 */
final class CorsListenerTest extends WebTestCase
{
    private const BASE_DOMAIN = 'localhost';

    public function testAllowedOriginGetsCorsHeaders(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/health', server: [
            'HTTP_HOST' => 'api.' . self::BASE_DOMAIN,
            'HTTP_ORIGIN' => 'http://admin.' . self::BASE_DOMAIN,
        ]);

        $response = $client->getResponse();
        self::assertSame('http://admin.localhost', $response->headers->get('Access-Control-Allow-Origin'));
        self::assertSame('true', $response->headers->get('Access-Control-Allow-Credentials'));
        self::assertStringContainsString('Origin', (string) $response->headers->get('Vary'));
    }

    public function testBaseDomainItselfIsAnAllowedOrigin(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/health', server: [
            'HTTP_HOST' => 'api.' . self::BASE_DOMAIN,
            'HTTP_ORIGIN' => 'http://' . self::BASE_DOMAIN . ':3000',
        ]);

        self::assertSame('http://localhost:3000', $client->getResponse()->headers->get('Access-Control-Allow-Origin'));
    }

    public function testForeignOriginGetsNoCorsHeaders(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/health', server: [
            'HTTP_HOST' => 'api.' . self::BASE_DOMAIN,
            'HTTP_ORIGIN' => 'http://evil.example',
        ]);

        self::assertFalse($client->getResponse()->headers->has('Access-Control-Allow-Origin'));
    }

    public function testLookalikeOriginIsRejected(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/health', server: [
            'HTTP_HOST' => 'api.' . self::BASE_DOMAIN,
            'HTTP_ORIGIN' => 'http://admin.localhost.evil.example',
        ]);

        self::assertFalse($client->getResponse()->headers->has('Access-Control-Allow-Origin'));
    }

    public function testRequestWithoutOriginIsUntouched(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/health', server: ['HTTP_HOST' => 'api.' . self::BASE_DOMAIN]);

        self::assertResponseIsSuccessful();
        self::assertFalse($client->getResponse()->headers->has('Access-Control-Allow-Origin'));
    }

    public function testPreflightIsAnsweredWithoutRouting(): void
    {
        $client = static::createClient();
        // /api/admin/login only accepts POST — a routed OPTIONS would be a 405,
        // so a 204 proves the listener answered before routing ran.
        $client->request('OPTIONS', '/api/admin/login', server: [
            'HTTP_HOST' => 'api.' . self::BASE_DOMAIN,
            'HTTP_ORIGIN' => 'http://admin.' . self::BASE_DOMAIN,
            'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'POST',
        ]);

        $response = $client->getResponse();
        self::assertSame(204, $response->getStatusCode());
        self::assertSame('GET, POST, PUT, PATCH, DELETE, OPTIONS', $response->headers->get('Access-Control-Allow-Methods'));
        self::assertSame('Content-Type, Authorization', $response->headers->get('Access-Control-Allow-Headers'));
    }

    public function testPreflightFromForeignOriginIsNotAnswered(): void
    {
        $client = static::createClient();
        $client->request('OPTIONS', '/api/admin/login', server: [
            'HTTP_HOST' => 'api.' . self::BASE_DOMAIN,
            'HTTP_ORIGIN' => 'http://evil.example',
            'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'POST',
        ]);

        self::assertNotSame(204, $client->getResponse()->getStatusCode());
    }
}
