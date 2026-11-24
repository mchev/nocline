<?php

namespace App\Modules\Wallboard\Models;

use App\Modules\Groups\Models\Group;
use App\Modules\Wallboard\Policies\WallboardPolicy;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $name
 * @property string $token_hash
 * @property int|null $group_id
 */
#[Fillable(['name', 'group_id'])]
#[Hidden(['token_hash'])]
#[UsePolicy(WallboardPolicy::class)]
class Wallboard extends Model
{
    public static function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    /**
     * Create a wallboard; returns it with the plaintext token (shown once).
     *
     * @param  array{name: string, group_id?: int|null}  $attributes
     * @return array{wallboard: self, token: string}
     */
    public static function createWithToken(array $attributes): array
    {
        $token = 'wb_'.Str::random(48);
        $wallboard = new self($attributes);
        $wallboard->token_hash = self::hashToken($token);
        $wallboard->save();

        return ['wallboard' => $wallboard, 'token' => $token];
    }

    public static function findByToken(string $token): ?self
    {
        return static::query()->where('token_hash', self::hashToken($token))->first();
    }

    /**
     * Issue a new secret URL. The previous token stops working immediately.
     */
    public function regenerateToken(): string
    {
        $token = 'wb_'.Str::random(48);
        $this->forceFill(['token_hash' => self::hashToken($token)])->save();

        return $token;
    }

    /**
     * @return BelongsTo<Group, $this>
     */
    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }
}
