<?php

namespace App\Modules\Checks\Enums;

enum CheckType: string
{
    case Http = 'http';
    case Tcp = 'tcp';
    case Icmp = 'icmp';
    case Dns = 'dns';
    case Agent = 'agent';
    case Heartbeat = 'heartbeat';
}
