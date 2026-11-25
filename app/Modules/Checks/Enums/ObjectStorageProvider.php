<?php

namespace App\Modules\Checks\Enums;

enum ObjectStorageProvider: string
{
    case Ovh = 'ovh';
    case Hetzner = 'hetzner';
    case LaravelCloud = 'laravel_cloud';
    case Aws = 'aws';
    case Scaleway = 'scaleway';
    case Wasabi = 'wasabi';
    case Custom = 'custom';

    public function label(): string
    {
        return match ($this) {
            self::Ovh => __('OVHcloud'),
            self::Hetzner => __('Hetzner Object Storage'),
            self::LaravelCloud => __('Laravel Cloud'),
            self::Aws => __('Amazon AWS'),
            self::Scaleway => __('Scaleway'),
            self::Wasabi => __('Wasabi'),
            self::Custom => __('Other / MinIO'),
        };
    }

    public function needsEndpointHost(): bool
    {
        return $this === self::Custom || $this === self::LaravelCloud;
    }
}
