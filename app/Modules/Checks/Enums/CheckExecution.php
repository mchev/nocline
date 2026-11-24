<?php

namespace App\Modules\Checks\Enums;

/**
 * Where a check runs: on the hub itself or on remote probes.
 */
enum CheckExecution: string
{
    case Hub = 'hub';
    case Probe = 'probe';
}
