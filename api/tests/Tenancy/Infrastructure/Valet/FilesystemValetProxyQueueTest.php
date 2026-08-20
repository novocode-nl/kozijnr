<?php

namespace App\Tests\Tenancy\Infrastructure\Valet;

use App\Tenancy\Infrastructure\Valet\FilesystemValetProxyQueue;
use Psr\Log\LoggerInterface;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the KOZ-12 (rework) file-based proxy queue writer. This
 * only ever performs plain filesystem I/O against a throwaway temp
 * directory — it never shells out to `valet`, and can't: see
 * ValetProxyQueueInterface's docblock for why this class is deliberately
 * incapable of calling the real `valet` binary at all. scripts/valet-sync.sh
 * (run on the host, outside this test suite entirely) is what actually
 * drains a queue directory like this one.
 */
final class FilesystemValetProxyQueueTest extends TestCase
{
    private string $queueDirectory;

    protected function setUp(): void
    {
        $this->queueDirectory = sys_get_temp_dir() . '/koz-12-valet-queue-test-' . bin2hex(random_bytes(4));
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->queueDirectory);
    }

    public function testWritesAPendingRequestFileContainingTheDomainAndTarget(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::never())->method('warning');

        $queue = new FilesystemValetProxyQueue($logger, $this->queueDirectory);
        $queue->enqueue('acme.kozijnr.test', 'http://127.0.0.1:8000');

        $files = $this->pendingFiles();
        self::assertCount(1, $files);

        $contents = json_decode(file_get_contents($files[0]), true, flags: JSON_THROW_ON_ERROR);
        self::assertSame('acme.kozijnr.test', $contents['domain']);
        self::assertSame('http://127.0.0.1:8000', $contents['target']);
    }

    public function testCreatesThePendingDirectoryWhenItDoesNotExistYet(): void
    {
        self::assertDirectoryDoesNotExist($this->queueDirectory);

        $queue = new FilesystemValetProxyQueue($this->createStub(LoggerInterface::class), $this->queueDirectory);
        $queue->enqueue('acme.kozijnr.test', 'http://127.0.0.1:8000');

        self::assertDirectoryExists($this->queueDirectory . '/pending');
    }

    public function testWritesASeparateFilePerRequestSoConcurrentTenantCreationsNeverCollide(): void
    {
        $queue = new FilesystemValetProxyQueue($this->createStub(LoggerInterface::class), $this->queueDirectory);

        $queue->enqueue('acme.kozijnr.test', 'http://127.0.0.1:8000');
        $queue->enqueue('beta.kozijnr.test', 'http://127.0.0.1:8000');

        self::assertCount(2, $this->pendingFiles());
    }

    public function testLogsAWarningAndDoesNotThrowWhenTheQueueDirectoryCannotBeCreated(): void
    {
        // A regular file where a directory needs to exist: mkdir() must fail.
        $blockingFile = sys_get_temp_dir() . '/koz-12-valet-queue-blocking-' . bin2hex(random_bytes(4));
        file_put_contents($blockingFile, 'not a directory');

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())
            ->method('warning')
            ->with(self::stringContains('Could not enqueue'), self::anything());

        $queue = new FilesystemValetProxyQueue($logger, $blockingFile);

        // No exception should escape this call.
        $queue->enqueue('acme.kozijnr.test', 'http://127.0.0.1:8000');

        unlink($blockingFile);
    }

    /** @return list<string> */
    private function pendingFiles(): array
    {
        $pendingDirectory = $this->queueDirectory . '/pending';

        if (!is_dir($pendingDirectory)) {
            return [];
        }

        $files = glob($pendingDirectory . '/*.json');

        return $files === false ? [] : $files;
    }

    private function removeDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }

        $items = scandir($directory);
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $directory . '/' . $item;
            is_dir($path) ? $this->removeDirectory($path) : unlink($path);
        }

        rmdir($directory);
    }
}
