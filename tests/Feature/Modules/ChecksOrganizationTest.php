<?php

use App\Models\User;
use App\Modules\Checks\Enums\CheckCategory;
use App\Modules\Checks\Models\Check;
use App\Modules\Checks\Support\HistoryWindow;
use App\Modules\Groups\Models\Group;

it('lists checks with group, category and tags for grouping', function () {
    $group = Group::factory()->create(['name' => 'Acme']);
    $check = Check::factory()->create([
        'group_id' => $group->id,
        'category' => CheckCategory::Mail,
        'tags' => ['prod'],
        'created_at' => now()->subDay(),
    ]);
    $check->state()->update(['status' => 'up']);

    $this->actingAs(User::factory()->tech()->create())
        ->get(route('checks.index'))
        ->assertInertia(fn ($page) => $page->component('checks/Index')
            ->has('checks', 1)
            ->where('checks.0.group_id', $group->id)->where('groups.0.path', 'Acme')
            ->where('checks.0.category', 'mail')
            ->where('checks.0.tags', ['prod'])
            ->has('checks.0.history', HistoryWindow::dashboard()->buckets)
            ->has('checks.0.timeline', HistoryWindow::dashboard()->buckets)
            ->has('categories', 6));
});

it('bulk-assigns group, category and tags', function () {
    $group = Group::factory()->create();
    $checks = Check::factory()->count(2)->create(['tags' => ['old']]);

    $this->actingAs(User::factory()->tech()->create())
        ->post(route('checks.bulk'), [
            'ids' => $checks->pluck('id')->all(),
            'action' => 'assign',
            'group_id' => $group->id,
            'category' => 'server',
            'add_tags' => ['eu', 'old'],
        ])->assertRedirect();

    $checks->each(function (Check $check) use ($group) {
        $check->refresh();
        expect($check->group_id)->toBe($group->id)
            ->and($check->category)->toBe(CheckCategory::Server)
            ->and($check->tags)->toBe(['old', 'eu']);
    });
});

it('bulk pauses and resumes checks', function () {
    $check = Check::factory()->create();
    $tech = User::factory()->tech()->create();

    $this->actingAs($tech)->post(route('checks.bulk'), ['ids' => [$check->id], 'action' => 'disable']);
    expect($check->refresh()->enabled)->toBeFalse();

    $this->actingAs($tech)->post(route('checks.bulk'), ['ids' => [$check->id], 'action' => 'enable']);
    expect($check->refresh()->enabled)->toBeTrue();
});

it('only lets admins bulk-delete and never viewers modify', function () {
    $check = Check::factory()->create();

    $this->actingAs(User::factory()->tech()->create())
        ->post(route('checks.bulk'), ['ids' => [$check->id], 'action' => 'delete'])->assertForbidden();
    $this->actingAs(User::factory()->create(['role' => 'viewer']))
        ->post(route('checks.bulk'), ['ids' => [$check->id], 'action' => 'disable'])->assertForbidden();
    $this->actingAs(User::factory()->admin()->create())
        ->post(route('checks.bulk'), ['ids' => [$check->id], 'action' => 'delete'])->assertRedirect();

    expect(Check::count())->toBe(0);
});
