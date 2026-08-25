<?php

namespace App\Tests\Shared\Infrastructure\Storage;

use App\Shared\Infrastructure\Storage\FlysystemAdapterFactory;
use League\Flysystem\AwsS3V3\AwsS3V3Adapter;
use League\Flysystem\FilesystemOperator;
use League\Flysystem\Local\LocalFilesystemAdapter;
use PHPUnit\Framework\TestCase;

/**
 * The config switch (KOZ-32 DoD: "Config-switch tussen local en
 * S3-compatible adapter is gedocumenteerd") is this factory: given the
 * `adapter` string (from APP_STORAGE_ADAPTER, see services.yaml), it
 * builds a FilesystemOperator backed by the matching Flysystem adapter.
 * No adapter-consuming code (FlysystemFileStorage, or anything above it)
 * ever needs to change to switch between them.
 */
final class FlysystemAdapterFactoryTest extends TestCase
{
    public function testLocalAdapterBuildsAFilesystemRootedAtTheGivenPath(): void
    {
        $root = sys_get_temp_dir() . '/kozijnr-adapter-factory-test-' . bin2hex(random_bytes(8));

        $operator = FlysystemAdapterFactory::create(
            adapter: 'local',
            localRoot: $root,
            s3Bucket: null,
            s3Region: null,
            s3Endpoint: null,
            s3Key: null,
            s3Secret: null,
        );

        self::assertInstanceOf(FilesystemOperator::class, $operator);

        // Prove it is actually rooted at $root and usable end to end.
        $operator->write('probe.txt', 'hello');
        self::assertSame('hello', file_get_contents($root . '/probe.txt'));
    }

    public function testS3AdapterBuildsAFilesystemBackedByAnS3Client(): void
    {
        $operator = FlysystemAdapterFactory::create(
            adapter: 's3',
            localRoot: null,
            s3Bucket: 'my-bucket',
            s3Region: 'ams3',
            s3Endpoint: 'https://ams3.digitaloceanspaces.com',
            s3Key: 'key',
            s3Secret: 'secret',
        );

        self::assertInstanceOf(FilesystemOperator::class, $operator);
    }

    public function testAnUnknownAdapterNameThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        FlysystemAdapterFactory::create(
            adapter: 'ftp',
            localRoot: null,
            s3Bucket: null,
            s3Region: null,
            s3Endpoint: null,
            s3Key: null,
            s3Secret: null,
        );
    }

    public function testLocalAdapterWithoutARootThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        FlysystemAdapterFactory::create(
            adapter: 'local',
            localRoot: null,
            s3Bucket: null,
            s3Region: null,
            s3Endpoint: null,
            s3Key: null,
            s3Secret: null,
        );
    }

    public function testS3AdapterMissingRequiredConfigThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        FlysystemAdapterFactory::create(
            adapter: 's3',
            localRoot: null,
            s3Bucket: null,
            s3Region: 'ams3',
            s3Endpoint: 'https://ams3.digitaloceanspaces.com',
            s3Key: 'key',
            s3Secret: 'secret',
        );
    }
}
