<?php

use App\Modules\Checks\Services\NetworkGuard;

it('refuses non-public addresses and accepts public ones', function (string $ip, bool $public) {
    expect((new NetworkGuard)->isPublicAddress($ip))->toBe($public);
})->with([
    ['93.184.216.34', true],
    ['8.8.8.8', true],
    ['2606:4700:4700::1111', true],
    ['127.0.0.1', false],
    ['10.1.2.3', false],
    ['172.16.0.1', false],
    ['172.32.0.1', true],
    ['192.168.1.1', false],
    ['169.254.169.254', false],
    ['100.64.0.1', false],
    ['0.0.0.0', false],
    ['224.0.0.1', false],
    ['::1', false],
    ['::', false],
    ['fe80::1', false],
    ['fd00:ec2::254', false],
    ['::ffff:127.0.0.1', false],
    ['::ffff:7f00:1', false],
    ['::ffff:8.8.8.8', true],
    ['64:ff9b::7f00:1', false],
    ['2002:7f00:1::', false],
    ['not-an-ip', false],
]);

it('flags numeric spellings of IPv4 addresses that are not plain dotted-quad', function (string $host, bool $obfuscated) {
    expect((new NetworkGuard)->isObfuscatedIpv4($host))->toBe($obfuscated);
})->with([
    ['2130706433', true],
    ['0x7f000001', true],
    ['0177.0.0.1', true],
    ['127.1', true],
    ['127.0.0.1', false],
    ['example.com', false],
    ['123.com', false],
]);
