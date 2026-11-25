<?php

namespace App\Modules\Checks\Support;

use App\Modules\Checks\Models\Check;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

final class CheckFaviconResponse
{
    public function file(Check $check, bool $publicCache = false): BinaryFileResponse
    {
        abort_unless($check->favicon_path !== null, 404);

        $disk = Storage::disk('local');
        abort_unless($disk->exists($check->favicon_path), 404);

        $mime = match (strtolower((string) pathinfo($check->favicon_path, PATHINFO_EXTENSION))) {
            'png' => 'image/png',
            'jpg', 'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            default => 'image/x-icon',
        };

        return response()->file($disk->path($check->favicon_path), [
            'Content-Type' => $mime,
            'Cache-Control' => $publicCache
                ? 'public, max-age=86400'
                : 'private, max-age=86400',
        ]);
    }
}
