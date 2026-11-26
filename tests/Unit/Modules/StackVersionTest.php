<?php

use App\Modules\Hosts\Support\StackVersion;

it('parses dotted versions and strips distro suffix', function () {
    expect(StackVersion::parse('8.1.26'))->toMatchArray([
        'major' => 8,
        'minor' => 1,
        'patch' => 26,
    ])->and(StackVersion::parse('8.0.40-0ubuntu0.22.04.5')['major'])->toBe(8)
        ->and(StackVersion::parse('22.14.0')['major'])->toBe(22);
});

it('compares dotted versions for patch freshness', function () {
    expect(StackVersion::isOlderThan('7.0.12', '7.0.15'))->toBeTrue()
        ->and(StackVersion::isOlderThan('8.5.13', '8.5.13'))->toBeFalse();
});
