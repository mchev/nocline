<?php

namespace Phare\Laravel\Facades;

use Illuminate\Support\Facades\Facade;
use Phare\Laravel\PhareClient;

/**
 * @method static bool heartbeat(?string $tokenOrName = null, ?string $message = null)
 * @method static bool fail(?string $tokenOrName = null, ?string $message = null)
 * @method static string|null urlFor(?string $tokenOrName = null, bool $fail = false)
 *
 * @see PhareClient
 */
class Phare extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return PhareClient::class;
    }
}
