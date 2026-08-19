<?php

namespace App\Tests\Tenancy\Infrastructure\Valet;

use App\Tenancy\Infrastructure\Valet\SymfonyProcessValetProxyRunner;
use Psr\Log\LoggerInterface;
use PHPUnit\Framework\TestCase;

/**
 * Runs the *real* SymfonyProcessValetProxyRunner (real Symfony Process
 * component, no mocking of it) but only inside this test's own environment
 * (the backend Docker container in CI/local dev), which never has a
 * `valet` binary on PATH at all — the host machine's actual Valet install
 * (if any) is never touched by this test. That absence is exactly what's
 * exercised here: `valet` missing entirely must be logged and swallowed,
 * never allowed to propagate and break the caller (a Doctrine postPersist
 * listener mid-flush — see TenantValetProxyListener).
 */
final class SymfonyProcessValetProxyRunnerTest extends TestCase
{
    public function testLogsAWarningAndDoesNotThrowWhenTheValetBinaryIsNotAvailable(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())
            ->method('warning')
            ->with(self::logicalOr(
                self::stringContains('could not be run'),
                self::stringContains('registration failed'),
            ), self::anything());

        $runner = new SymfonyProcessValetProxyRunner($logger);

        // No exception should escape this call.
        $runner->proxy('acme.kozijnr.test', 'http://127.0.0.1:8000');
    }
}
