<?php

namespace App\Tests\Tenancy\Infrastructure\Valet;

use App\Tenancy\Domain\Tenant;
use App\Tenancy\Infrastructure\Valet\TenantValetProxyListener;
use App\Tenancy\Infrastructure\Valet\ValetProxyRunnerInterface;
use Doctrine\ORM\Event\PostPersistEventArgs;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the KOZ-12 dev-only tenant auto-proxy listener, entirely
 * against a mocked ValetProxyRunnerInterface — never touches a real `valet`
 * process, let alone the host's actual Valet installation. See
 * SymfonyProcessValetProxyRunnerTest for coverage of the real runner
 * implementation (still safe: it runs inside the backend Docker container,
 * which has no `valet` binary at all).
 */
final class TenantValetProxyListenerTest extends TestCase
{
    public function testRegistersAValetProxyForANewlyPersistedTenantInTheDevEnvironment(): void
    {
        $tenant = new Tenant('acme', 'tenant_acme');

        $proxyRunner = $this->createMock(ValetProxyRunnerInterface::class);
        $proxyRunner->expects(self::once())
            ->method('proxy')
            ->with('acme.kozijnr.test', 'http://127.0.0.1:8000');

        $listener = new TenantValetProxyListener($proxyRunner, 'dev', 'kozijnr.test', 8000);
        $listener->postPersist($this->eventArgsFor($tenant));
    }

    public function testUsesTheConfiguredBaseDomainAndBackendPort(): void
    {
        $tenant = new Tenant('acme', 'tenant_acme');

        $proxyRunner = $this->createMock(ValetProxyRunnerInterface::class);
        $proxyRunner->expects(self::once())
            ->method('proxy')
            ->with('acme.kozijnr-koz-12.test', 'http://127.0.0.1:8012');

        $listener = new TenantValetProxyListener($proxyRunner, 'dev', 'kozijnr-koz-12.test', 8012);
        $listener->postPersist($this->eventArgsFor($tenant));
    }

    /** @return iterable<string, array{0: string}> */
    public static function nonDevEnvironmentProvider(): iterable
    {
        yield 'test' => ['test'];
        yield 'prod' => ['prod'];
        yield 'staging' => ['staging'];
    }

    #[DataProvider('nonDevEnvironmentProvider')]
    public function testNeverCallsTheProxyRunnerOutsideTheDevEnvironment(string $environment): void
    {
        $tenant = new Tenant('acme', 'tenant_acme');

        $proxyRunner = $this->createMock(ValetProxyRunnerInterface::class);
        $proxyRunner->expects(self::never())->method('proxy');

        $listener = new TenantValetProxyListener($proxyRunner, $environment, 'kozijnr.test', 8000);
        $listener->postPersist($this->eventArgsFor($tenant));
    }

    public function testIgnoresPostPersistEventsForEntitiesOtherThanTenant(): void
    {
        $proxyRunner = $this->createMock(ValetProxyRunnerInterface::class);
        $proxyRunner->expects(self::never())->method('proxy');

        $listener = new TenantValetProxyListener($proxyRunner, 'dev', 'kozijnr.test', 8000);
        $listener->postPersist($this->eventArgsFor(new \stdClass()));
    }

    private function eventArgsFor(object $entity): PostPersistEventArgs
    {
        return new PostPersistEventArgs($entity, $this->createStub(EntityManagerInterface::class));
    }
}
