<?php

namespace App\Modules\Checks\Support;

use App\Modules\Checks\Enums\ObjectStorageLevel;
use App\Modules\Checks\Enums\ObjectStorageProvider;
use InvalidArgumentException;

/**
 * S3-compatible endpoint patterns for the guided object-storage wizard.
 */
final class ObjectStoragePresets
{
    /**
     * @return list<array{value: string, label: string, regions: list<array{value: string, label: string}>}>
     */
    public static function forFrontend(): array
    {
        $out = [];

        foreach (ObjectStorageProvider::cases() as $provider) {
            $out[] = [
                'value' => $provider->value,
                'label' => $provider->label(),
                'regions' => self::regionsForFrontend($provider),
                'needs_endpoint' => $provider->needsEndpointHost(),
                'show_region_with_endpoint' => $provider === ObjectStorageProvider::LaravelCloud,
            ];
        }

        return $out;
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private static function regionsForFrontend(ObjectStorageProvider $provider): array
    {
        return array_map(
            static fn (string $code) => ['value' => $code, 'label' => strtoupper($code)],
            self::regionCodes($provider),
        );
    }

    /**
     * @return list<string>
     */
    public static function regionCodes(ObjectStorageProvider $provider): array
    {
        return match ($provider) {
            ObjectStorageProvider::Ovh => ['gra', 'sbg', 'rbx', 'bhs', 'de', 'uk', 'waw', 'sgp', 'syd'],
            ObjectStorageProvider::Hetzner => ['nbg1', 'fsn1', 'hel1'],
            ObjectStorageProvider::LaravelCloud => [
                'eu-west-3', 'eu-central-1', 'eu-west-1', 'eu-west-2', 'us-east-1', 'us-east-2',
            ],
            ObjectStorageProvider::Aws => ['eu-west-3', 'eu-west-1', 'eu-central-1', 'us-east-1', 'us-west-2'],
            ObjectStorageProvider::Scaleway => ['fr-par', 'nl-ams', 'pl-waw'],
            ObjectStorageProvider::Wasabi => ['eu-central-1', 'eu-west-1', 'eu-west-2', 'us-east-1'],
            ObjectStorageProvider::Custom => [],
        };
    }

    public static function endpointHost(
        ObjectStorageProvider $provider,
        string $region,
        ?string $customEndpoint = null,
    ): string {
        if ($provider->needsEndpointHost()) {
            $host = self::normalizeHost($customEndpoint ?? '');

            if ($host === '') {
                throw new InvalidArgumentException('Endpoint host is required.');
            }

            return $host;
        }

        $region = strtolower(trim($region));

        return match ($provider) {
            ObjectStorageProvider::Ovh => "s3.{$region}.io.cloud.ovh.net",
            ObjectStorageProvider::Aws => "s3.{$region}.amazonaws.com",
            ObjectStorageProvider::Scaleway => "s3.{$region}.scw.cloud",
            ObjectStorageProvider::Hetzner => "{$region}.your-objectstorage.com",
            ObjectStorageProvider::Wasabi => "s3.{$region}.wasabisys.com",
            ObjectStorageProvider::LaravelCloud, ObjectStorageProvider::Custom => throw new InvalidArgumentException('Unreachable'),
        };
    }

    public static function bucketObjectUrl(
        ObjectStorageProvider $provider,
        string $region,
        string $bucket,
        string $objectPath,
        ?string $customEndpoint = null,
    ): string {
        $bucket = self::sanitizeBucketName($bucket);
        $objectPath = ltrim(self::sanitizeObjectPath($objectPath), '/');

        if ($provider->needsEndpointHost()) {
            $host = self::endpointHost($provider, '', $customEndpoint);

            return "https://{$host}/{$bucket}/{$objectPath}";
        }

        $region = strtolower(trim($region));

        $host = match ($provider) {
            ObjectStorageProvider::Ovh => "{$bucket}.s3.{$region}.io.cloud.ovh.net",
            ObjectStorageProvider::Aws => "{$bucket}.s3.{$region}.amazonaws.com",
            ObjectStorageProvider::Scaleway => "{$bucket}.s3.{$region}.scw.cloud",
            ObjectStorageProvider::Hetzner => "{$bucket}.{$region}.your-objectstorage.com",
            ObjectStorageProvider::Wasabi => "{$bucket}.s3.{$region}.wasabisys.com",
            ObjectStorageProvider::LaravelCloud, ObjectStorageProvider::Custom => throw new InvalidArgumentException('Unreachable'),
        };

        return "https://{$host}/{$objectPath}";
    }

    public static function defaultMonitorName(
        ObjectStorageProvider $provider,
        string $region,
        ObjectStorageLevel $level,
        ?string $bucket = null,
    ): string {
        $label = $provider->label();
        $regionUp = strtoupper($region);

        return match ($level) {
            ObjectStorageLevel::Platform => __(':provider object storage (:region)', ['provider' => $label, 'region' => $regionUp]),
            ObjectStorageLevel::Bucket => __(':bucket on :provider', ['bucket' => $bucket ?? 'bucket', 'provider' => $label]),
            ObjectStorageLevel::Backup => __('Backup to :provider (:region)', ['provider' => $label, 'region' => $regionUp]),
        };
    }

    private static function normalizeHost(string $host): string
    {
        $host = trim($host);
        $host = preg_replace('#^https?://#i', '', $host) ?? $host;
        $host = strtok($host, '/:?') ?: '';

        return strtolower($host);
    }

    private static function sanitizeBucketName(string $bucket): string
    {
        $bucket = trim($bucket);
        if (! preg_match('/^[a-z0-9][a-z0-9.\-]{1,61}[a-z0-9]$/', $bucket)) {
            throw new InvalidArgumentException('Invalid bucket name.');
        }

        return $bucket;
    }

    private static function sanitizeObjectPath(string $path): string
    {
        $path = trim($path);
        if ($path === '' || str_contains($path, '..')) {
            throw new InvalidArgumentException('Invalid object path.');
        }

        return $path;
    }
}
