<?php

namespace App\Shared\Infrastructure\Storage;

use Aws\S3\S3Client;
use League\Flysystem\AwsS3V3\AwsS3V3Adapter;
use League\Flysystem\Filesystem;
use League\Flysystem\FilesystemOperator;
use League\Flysystem\Local\LocalFilesystemAdapter;

/**
 * Builds the FilesystemOperator that FlysystemFileStorage is wired to,
 * choosing the concrete Flysystem adapter purely from config (KOZ-32 DoD:
 * switching between local dev storage and an S3-compatible production
 * bucket, e.g. DigitalOcean Spaces, is a config change only — no adapter
 * code changes).
 *
 * Wired as a factory service in config/services.yaml, fed by
 * APP_STORAGE_* env vars — see that file's comments for the full list and
 * .env for local defaults.
 */
final class FlysystemAdapterFactory
{
    /**
     * @param 'local'|'s3' $adapter
     */
    public static function create(
        string $adapter,
        ?string $localRoot,
        ?string $s3Bucket,
        ?string $s3Region,
        ?string $s3Endpoint,
        ?string $s3Key,
        ?string $s3Secret,
    ): FilesystemOperator {
        return match ($adapter) {
            'local' => self::createLocal($localRoot),
            's3' => self::createS3($s3Bucket, $s3Region, $s3Endpoint, $s3Key, $s3Secret),
            default => throw new \InvalidArgumentException(sprintf(
                'Unknown storage adapter "%s". Expected "local" or "s3" (see APP_STORAGE_ADAPTER in .env).',
                $adapter,
            )),
        };
    }

    private static function createLocal(?string $localRoot): FilesystemOperator
    {
        if ($localRoot === null || $localRoot === '') {
            throw new \InvalidArgumentException('The "local" storage adapter requires APP_STORAGE_LOCAL_ROOT to be set.');
        }

        return new Filesystem(new LocalFilesystemAdapter($localRoot));
    }

    private static function createS3(
        ?string $bucket,
        ?string $region,
        ?string $endpoint,
        ?string $key,
        ?string $secret,
    ): FilesystemOperator {
        if ($bucket === null || $bucket === '' || $region === null || $region === ''
            || $key === null || $key === '' || $secret === null || $secret === ''
        ) {
            throw new \InvalidArgumentException(
                'The "s3" storage adapter requires APP_STORAGE_S3_BUCKET, APP_STORAGE_S3_REGION, '
                . 'APP_STORAGE_S3_KEY and APP_STORAGE_S3_SECRET to all be set.',
            );
        }

        $config = [
            'credentials' => ['key' => $key, 'secret' => $secret],
            'region' => $region,
            'version' => 'latest',
        ];

        // An explicit endpoint is how an S3-*compatible* provider (e.g.
        // DigitalOcean Spaces) is targeted instead of real AWS S3; omitted
        // entirely for real AWS so the SDK resolves its own endpoint.
        if ($endpoint !== null && $endpoint !== '') {
            $config['endpoint'] = $endpoint;
        }

        $client = new S3Client($config);

        return new Filesystem(new AwsS3V3Adapter($client, $bucket));
    }
}
