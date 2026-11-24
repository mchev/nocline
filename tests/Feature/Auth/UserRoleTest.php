<?php

use App\Models\User;

it('lets admin and tech manage but not viewer', function () {
    expect(User::factory()->admin()->make()->canManage())->toBeTrue()
        ->and(User::factory()->tech()->make()->canManage())->toBeTrue()
        ->and(User::factory()->make()->canManage())->toBeFalse();
});
