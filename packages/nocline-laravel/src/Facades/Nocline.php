<?php

namespace Nocline\Laravel\Facades;

use Illuminate\Support\Facades\Facade;
use Nocline\Laravel\NoclineClient;

/**
 * @method static bool heartbeat(?string $tokenOrName = null, ?string $message = null)
 * @method static bool fail(?string $tokenOrName = null, ?string $message = null)
 * @method static string|null urlFor(?string $tokenOrName = null, bool $fail = false)
 *
 * @see NoclineClient
 */
class Nocline extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return NoclineClient::class;
    }
}
