<?php

namespace App\Modules\Coffre\Models;

use App\Modules\Coffre\Policies\VaultEntryPolicy;
use App\Modules\Coffre\Services\VaultCrypto;
use App\Modules\Groups\Models\Group;
use App\Modules\Hosts\Models\Host;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Encrypted fields are never mapped to plain attributes: use seal()/reveal().
 *
 * @property int $id
 * @property int|null $group_id
 * @property int|null $host_id
 * @property string $label
 * @property string|null $url
 * @property string|null $username_enc
 * @property string $secret_enc
 * @property string|null $notes_enc
 * @property string $dek_wrapped
 */
#[Hidden(['username_enc', 'secret_enc', 'notes_enc', 'dek_wrapped'])]
#[UsePolicy(VaultEntryPolicy::class)]
class VaultEntry extends Model
{
    protected $guarded = [];

    /**
     * Encrypt the secret fields with a fresh DEK (or the existing one when updating).
     *
     * @param  array{username?: string|null, secret: string, notes?: string|null}  $plain
     */
    public function seal(array $plain, VaultCrypto $crypto): void
    {
        $dek = $this->dek_wrapped ? $crypto->unwrapDek($this->dek_wrapped) : $crypto->newDek();

        $this->dek_wrapped = $crypto->wrapDek($dek);
        $this->secret_enc = $crypto->encrypt($plain['secret'], $dek);
        $this->username_enc = ($plain['username'] ?? '') !== '' ? $crypto->encrypt($plain['username'], $dek) : null;
        $this->notes_enc = ($plain['notes'] ?? '') !== '' ? $crypto->encrypt($plain['notes'], $dek) : null;

        sodium_memzero($dek);
    }

    /**
     * @return array{username: string|null, secret: string, notes: string|null}
     */
    public function reveal(VaultCrypto $crypto): array
    {
        $dek = $crypto->unwrapDek($this->dek_wrapped);

        $out = [
            'username' => $this->username_enc ? $crypto->decrypt($this->username_enc, $dek) : null,
            'secret' => $crypto->decrypt($this->secret_enc, $dek),
            'notes' => $this->notes_enc ? $crypto->decrypt($this->notes_enc, $dek) : null,
        ];

        sodium_memzero($dek);

        return $out;
    }

    /**
     * @return BelongsTo<Group, $this>
     */
    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    /**
     * @return BelongsTo<Host, $this>
     */
    public function host(): BelongsTo
    {
        return $this->belongsTo(Host::class);
    }
}
