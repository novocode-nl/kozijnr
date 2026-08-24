<?php

namespace App\Tests\Shared\Infrastructure\Storage;

use App\Shared\Domain\Storage\Exception\FileNotFoundException;
use App\Shared\Infrastructure\Storage\FlysystemFileStorage;
use League\Flysystem\Filesystem;
use League\Flysystem\Local\LocalFilesystemAdapter;
use PHPUnit\Framework\TestCase;

/**
 * Integration test against a real local adapter rooted in a temp
 * directory — proves FlysystemFileStorage correctly delegates to whatever
 * Flysystem adapter it is given, without caring which one.
 */
final class FlysystemFileStorageTest extends TestCase
{
    private string $root;
    private FlysystemFileStorage $storage;

    protected function setUp(): void
    {
        parent::setUp();

        $this->root = sys_get_temp_dir() . '/kozijnr-flysystem-test-' . bin2hex(random_bytes(8));
        mkdir($this->root, 0777, true);

        $filesystem = new Filesystem(new LocalFilesystemAdapter($this->root));
        $this->storage = new FlysystemFileStorage($filesystem);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->root);

        parent::tearDown();
    }

    public function testWriteThenReadRoundTripsTheExactContents(): void
    {
        $this->storage->write('profile-photos/1/photo.jpg', 'binary-content');

        self::assertSame('binary-content', $this->storage->read('profile-photos/1/photo.jpg'));
    }

    public function testExistsIsFalseBeforeWriteAndTrueAfter(): void
    {
        self::assertFalse($this->storage->exists('profile-photos/1/photo.jpg'));

        $this->storage->write('profile-photos/1/photo.jpg', 'binary-content');

        self::assertTrue($this->storage->exists('profile-photos/1/photo.jpg'));
    }

    public function testDeleteRemovesTheFile(): void
    {
        $this->storage->write('profile-photos/1/photo.jpg', 'binary-content');
        $this->storage->delete('profile-photos/1/photo.jpg');

        self::assertFalse($this->storage->exists('profile-photos/1/photo.jpg'));
    }

    public function testReadingAMissingKeyThrowsFileNotFoundException(): void
    {
        $this->expectException(FileNotFoundException::class);

        $this->storage->read('profile-photos/does-not-exist.jpg');
    }

    public function testDeletingAMissingKeyIsANoOp(): void
    {
        // No exception expected — deleting something already gone is fine.
        $this->storage->delete('profile-photos/does-not-exist.jpg');

        self::assertFalse($this->storage->exists('profile-photos/does-not-exist.jpg'));
    }

    private function removeDirectory(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        $items = scandir($path);
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $itemPath = $path . '/' . $item;
            if (is_dir($itemPath)) {
                $this->removeDirectory($itemPath);
            } else {
                unlink($itemPath);
            }
        }

        rmdir($path);
    }
}
