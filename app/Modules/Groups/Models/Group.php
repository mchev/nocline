<?php

namespace App\Modules\Groups\Models;

use App\Modules\Groups\Enums\GroupLayout;
use App\Modules\Groups\Policies\GroupPolicy;
use Carbon\CarbonInterface;
use Database\Factories\GroupFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A folder of monitored things (servers, websites, anything). Groups nest freely.
 *
 * @property int $id
 * @property int|null $parent_id
 * @property string $name
 * @property string|null $color
 * @property GroupLayout $layout
 * @property bool $collapsed
 * @property int $position
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 */
#[Fillable(['parent_id', 'name', 'color', 'layout', 'collapsed', 'position'])]
#[UseFactory(GroupFactory::class)]
#[UsePolicy(GroupPolicy::class)]
class Group extends Model
{
    /** @use HasFactory<GroupFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'layout' => GroupLayout::class,
            'collapsed' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Group, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /**
     * @return HasMany<Group, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    /**
     * Delete the group: its sub-groups move up one level, the things it held become ungrouped.
     */
    public function dissolve(): void
    {
        $this->children()->update(['parent_id' => $this->parent_id]);
        $this->delete();
    }

    /**
     * This group's id followed by the ids of all its descendants.
     *
     * @return list<int>
     */
    public function selfAndDescendantIds(): array
    {
        $childrenByParent = static::query()->whereNotNull('parent_id')->get(['id', 'parent_id'])->groupBy('parent_id');
        $ids = [$this->id];

        for ($i = 0; $i < count($ids); $i++) {
            foreach ($childrenByParent->get($ids[$i], []) as $child) {
                $ids[] = $child->id;
            }
        }

        return $ids;
    }

    /**
     * Whether $candidateParentId is this group or one of its descendants (which would create a loop).
     */
    public function wouldLoopUnder(?int $candidateParentId): bool
    {
        return $candidateParentId !== null && in_array($candidateParentId, $this->selfAndDescendantIds(), true);
    }

    /**
     * Every group as a flat, tree-ordered list; `path` is the full label ("Acme / Production").
     *
     * @return list<array{id: int, parent_id: int|null, name: string, path: string, depth: int, color: string|null, layout: string, collapsed: bool, position: int}>
     */
    public static function options(): array
    {
        $groups = static::query()->orderBy('position')->orderBy('name')->get();
        $byParent = $groups->groupBy(fn (Group $g) => $g->parent_id ?? 0);
        $options = [];

        $walk = function (int $parentId, string $prefix, int $depth) use (&$walk, $byParent, &$options): void {
            foreach ($byParent->get($parentId, []) as $group) {
                $path = $prefix.$group->name;
                $options[] = [
                    'id' => $group->id,
                    'parent_id' => $group->parent_id,
                    'name' => $group->name,
                    'path' => $path,
                    'depth' => $depth,
                    'color' => $group->color,
                    'layout' => $group->layout->value,
                    'collapsed' => $group->collapsed,
                    'position' => $group->position,
                ];
                $walk($group->id, $path.' / ', $depth + 1);
            }
        };
        $walk(0, '', 0);

        return $options;
    }
}
