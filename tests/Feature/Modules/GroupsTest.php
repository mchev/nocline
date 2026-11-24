<?php

use App\Models\User;
use App\Modules\Checks\Models\Check;
use App\Modules\Groups\Models\Group;

it('lists groups as a tree with full paths and counts', function () {
    $parent = Group::factory()->create(['name' => 'Acme']);
    $child = Group::factory()->create(['name' => 'Prod', 'parent_id' => $parent->id]);
    Check::factory()->create(['group_id' => $child->id]);

    $this->actingAs(User::factory()->create())
        ->get(route('groups.index'))
        ->assertInertia(fn ($page) => $page->component('groups/Index')
            ->where('groups.0.path', 'Acme')
            ->where('groups.1.path', 'Acme / Prod')
            ->where('groups.1.depth', 1)
            ->where('groups.1.checks_count', 1));
});

it('lets tech create and update a group with display options', function () {
    $this->actingAs(User::factory()->tech()->create());

    $this->post(route('groups.store'), ['name' => 'Servers', 'layout' => 'grid', 'color' => '#22AA66'])->assertRedirect();

    $group = Group::first();
    expect($group->layout->value)->toBe('grid')->and($group->color)->toBe('#22AA66');

    $this->patch(route('groups.update', $group), ['name' => 'Servers', 'collapsed' => true, 'layout' => 'compact'])->assertRedirect();
    expect($group->refresh()->collapsed)->toBeTrue()->and($group->layout->value)->toBe('compact');
});

it('rejects invalid display options', function () {
    $this->actingAs(User::factory()->tech()->create());

    $this->post(route('groups.store'), ['name' => 'X', 'layout' => 'cube', 'color' => 'red'])
        ->assertSessionHasErrors(['layout', 'color']);
});

it('refuses to put a group inside itself or its descendants', function () {
    $this->actingAs(User::factory()->tech()->create());
    $a = Group::factory()->create();
    $b = Group::factory()->create(['parent_id' => $a->id]);

    $this->patch(route('groups.update', $a), ['name' => $a->name, 'parent_id' => $b->id])->assertSessionHasErrors('parent_id');
    $this->patch(route('groups.update', $a), ['name' => $a->name, 'parent_id' => $a->id])->assertSessionHasErrors('parent_id');
});

it('moves sub-groups up and ungroups monitors when a group is deleted', function () {
    $this->actingAs(User::factory()->tech()->create());
    $top = Group::factory()->create();
    $mid = Group::factory()->create(['parent_id' => $top->id]);
    $leaf = Group::factory()->create(['parent_id' => $mid->id]);
    $check = Check::factory()->create(['group_id' => $mid->id]);

    $this->delete(route('groups.destroy', $mid))->assertRedirect();

    expect($leaf->refresh()->parent_id)->toBe($top->id)->and($check->refresh()->group_id)->toBeNull();
});

it('forbids viewers from writing', function () {
    $group = Group::factory()->create();
    $this->actingAs(User::factory()->create());

    $this->post(route('groups.store'), ['name' => 'X'])->assertForbidden();
    $this->patch(route('groups.update', $group), ['name' => 'X'])->assertForbidden();
    $this->delete(route('groups.destroy', $group))->assertForbidden();
});
