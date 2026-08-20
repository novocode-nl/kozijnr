<?php

namespace App\Tests\Tenancy\Infrastructure\Valet;

use App\Tenancy\Domain\Tenant;
use App\Tenancy\Infrastructure\Valet\TenantValetProxyListener;
use App\Tenancy\Infrastructure\Valet\ValetProxyQueueInterface;
use Doctrine\ORM\Event\PostPersistEventArgs;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the KOZ-12 dev-only tenant auto-proxy listener, entirely
 * against a mocked ValetProxyQueueInterface — never touches the real queue
 * directory, let alone the host's actual Valet installation. See
 * FilesystemValetProxyQueueTest for coverage of the real queue writer
 * implementation, and scripts/valet-sync.sh for what actually calls `valet
 * proxy` on the host.
 */
final class TenantValetProxyListenerTest extends TestCase
{
    public function testQueuesAValetProxyRequestForANewlyPersistedTenantInTheDevEnvironment(): void
    {
        $tenant = new Tenant('acme', 'tenant_acme');

        $proxyQueue = $this->createMock(ValetProxyQueueInterface::class);
        $proxyQueue->expects(self::once())
            ->method('enqueue')
            ->with('acme.kozijnr.test', 'http://127.0.0.1:8000');

        $listener = new TenantValetProxyListener($proxyQueue, 'dev', 'kozijnr.test', 8000);
        $listener->postPersist($this->eventArgsFor($tenant));
    }

    public function testUsesTheConfiguredBaseDomainAndBackendPort(): void
    {
        $tenant = new Tenant('acme', 'tenant_acme');

        $proxyQueue = $this->createMock(ValetProxyQueueInterface::class);
        $proxyQueue->expects(self::once())
            ->method('enqueue')
            ->with('acme.kozijnr-koz-12.test', 'http://127.0.0.1:8012');

        $listener = new TenantValetProxyListener($proxyQueue, 'dev', 'kozijnr-koz-12.test', 8012);
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
    public function testNeverQueuesAProxyRequestOutsideTheDevEnvironment(string $environment): void
    {
        $tenant = new Tenant('acme', 'tenant_acme');

        $proxyQueue = $this->createMock(ValetProxyQueueInterface::class);
        $proxyQueue->expects(self::never())->method('enqueue');

        $listener = new TenantValetProxyListener($proxyQueue, $environment, 'kozijnr.test', 8000);
        $listener->postPersist($this->eventArgsFor($tenant));
    }

    public function testIgnoresPostPersistEventsForEntitiesOtherThanTenant(): void
    {
        $proxyQueue = $this->createMock(ValetProxyQueueInterface::class);
        $proxyQueue->expects(self::never())->method('enqueue');

        $listener = new TenantValetProxyListener($proxyQueue, 'dev', 'kozijnr.test', 8000);
        $listener->postPersist($this->eventArgsFor(new \stdClass()));
    }

    private function eventArgsFor(object $entity): PostPersistEventArgs
    {
        return new PostPersistEventArgs($entity, $this->createStub(EntityManagerInterface::class));
    }
}
