<?php

namespace App\Modules\StatusPages\Models;

use App\Modules\Groups\Models\Group;
use App\Modules\StatusPages\Policies\StatusPagePolicy;
use Carbon\CarbonInterface;
use Database\Factories\StatusPageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * A public, branded page showing the health of a group (or of everything).
 *
 * @property int $id
 * @property string $slug
 * @property string $title
 * @property string|null $description
 * @property int|null $group_id
 * @property string|null $accent_color
 * @property string|null $logo_path
 * @property bool $show_incidents
 * @property bool $enabled
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 */
#[Fillable(['slug', 'title', 'description', 'group_id', 'accent_color', 'logo_path', 'show_incidents', 'enabled'])]
#[UseFactory(StatusPageFactory::class)]
#[UsePolicy(StatusPagePolicy::class)]
class StatusPage extends Model
{
    /** @use HasFactory<StatusPageFactory> */
    use HasFactory;

    public const LOGO_DISK = 'public';

    public const LOGO_DIRECTORY = 'status-pages';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'show_incidents' => 'boolean',
            'enabled' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Group, $this>
     */
    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    public function logoUrl(): ?string
    {
        return $this->logo_path === null ? null : Storage::disk(self::LOGO_DISK)->url($this->logo_path);
    }

    /**
     * Store a new logo on the public disk (the caller removes the previous one first).
     */
    public function storeLogo(UploadedFile $file): void
    {
        $this->logo_path = $file->store(self::LOGO_DIRECTORY, self::LOGO_DISK)
            ?: throw new RuntimeException('The logo could not be stored.');
    }

    public function deleteLogo(): void
    {
        if ($this->logo_path !== null) {
            Storage::disk(self::LOGO_DISK)->delete($this->logo_path);
        }
    }
}
