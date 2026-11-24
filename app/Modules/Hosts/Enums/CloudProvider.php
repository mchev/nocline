<?php

namespace App\Modules\Hosts\Enums;

enum CloudProvider: string
{
    case Hetzner = 'hetzner';
    case Aws = 'aws';
    case DigitalOcean = 'digitalocean';
    case Gcp = 'gcp';
    case Azure = 'azure';
    case Ovh = 'ovh';
    case Scaleway = 'scaleway';
    case Vultr = 'vultr';
    case Other = 'other';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function label(): string
    {
        return match ($this) {
            self::Hetzner => __('Hetzner'),
            self::Aws => __('AWS'),
            self::DigitalOcean => __('DigitalOcean'),
            self::Gcp => __('Google Cloud'),
            self::Azure => __('Azure'),
            self::Ovh => __('OVH'),
            self::Scaleway => __('Scaleway'),
            self::Vultr => __('Vultr'),
            self::Other => __('Other hoster'),
        };
    }
}
