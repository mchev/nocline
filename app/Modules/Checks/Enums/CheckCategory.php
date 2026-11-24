<?php

namespace App\Modules\Checks\Enums;

enum CheckCategory: string
{
    case Website = 'website';
    case Server = 'server';
    case Mail = 'mail';
    case Dns = 'dns';
    case Network = 'network';
    case Other = 'other';
}
