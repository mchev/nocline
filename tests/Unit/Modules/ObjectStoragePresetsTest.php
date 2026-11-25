<?php

use App\Modules\Checks\Enums\ObjectStorageProvider;
use App\Modules\Checks\Support\ObjectStoragePresets;

it('builds OVH platform and bucket URLs', function () {
    expect(ObjectStoragePresets::endpointHost(ObjectStorageProvider::Ovh, 'gra'))
        ->toBe('s3.gra.io.cloud.ovh.net');

    expect(ObjectStoragePresets::bucketObjectUrl(ObjectStorageProvider::Ovh, 'gra', 'my-bucket', 'health.txt'))
        ->toBe('https://my-bucket.s3.gra.io.cloud.ovh.net/health.txt');
});

it('builds custom path-style bucket URL', function () {
    expect(ObjectStoragePresets::bucketObjectUrl(
        ObjectStorageProvider::Custom,
        '',
        'backups',
        'health.txt',
        'minio.example.com',
    ))->toBe('https://minio.example.com/backups/health.txt');
});

it('builds Hetzner endpoints', function () {
    expect(ObjectStoragePresets::endpointHost(ObjectStorageProvider::Hetzner, 'nbg1'))
        ->toBe('nbg1.your-objectstorage.com');

    expect(ObjectStoragePresets::bucketObjectUrl(ObjectStorageProvider::Hetzner, 'fsn1', 'assets', 'health.txt'))
        ->toBe('https://assets.fsn1.your-objectstorage.com/health.txt');
});

it('uses Laravel Cloud custom R2 endpoint', function () {
    expect(ObjectStoragePresets::endpointHost(
        ObjectStorageProvider::LaravelCloud,
        '',
        'abc123.r2.cloudflarestorage.com',
    ))->toBe('abc123.r2.cloudflarestorage.com');
});
