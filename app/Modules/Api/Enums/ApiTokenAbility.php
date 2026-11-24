<?php

namespace App\Modules\Api\Enums;

/**
 * What a personal API token may do, on top of what its owner's role allows.
 */
enum ApiTokenAbility: string
{
    case Read = 'read';
    case Write = 'write';
}
