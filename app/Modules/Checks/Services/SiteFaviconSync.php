<?php

namespace App\Modules\Checks\Services;

use App\Modules\Checks\Enums\CheckCategory;
use App\Modules\Checks\Enums\CheckType;
use App\Modules\Checks\Models\Check;
use Illuminate\Support\Facades\Storage;

/**
 * Downloads and stores website favicons (SSRF-safe, same rules as site detection).
 */
class SiteFaviconSync
{
    private const int MAX_ICON_BYTES = 65536;

    public function __construct(private readonly SiteDetector $detector) {}

    public function appliesTo(Check $check): bool
    {
        return $check->type === CheckType::Http
            && $check->category === CheckCategory::Website
            && $check->target !== '';
    }

    public function sync(Check $check): bool
    {
        if (! $this->appliesTo($check)) {
            return false;
        }

        $page = $this->detector->fetchPage($check->target);

        if ($page === null || $page['status'] >= 400) {
            return false;
        }

        $origin = $this->origin($page['url']);
        $candidates = $this->iconCandidates($page['url'], $page['body'], $origin);

        foreach ($candidates as $iconUrl) {
            $bytes = $this->downloadIcon($iconUrl);

            if ($bytes !== null) {
                return $this->store($check, $bytes);
            }
        }

        return false;
    }

    /**
     * @return list<string>
     */
    private function iconCandidates(string $pageUrl, string $html, ?string $origin): array
    {
        $urls = [];
        $iconLinks = [];

        if (preg_match_all('/<link\b[^>]*>/i', $html, $matches)) {
            foreach ($matches[0] as $tag) {
                $rel = $this->attr($tag, 'rel') ?? '';
                $href = $this->attr($tag, 'href');

                if ($href === null || $href === '') {
                    continue;
                }

                if (! preg_match('/\b(icon|shortcut icon|apple-touch-icon)\b/i', $rel)) {
                    continue;
                }

                $resolved = $this->resolveUrl($pageUrl, $href);

                if ($resolved === null) {
                    continue;
                }

                $size = $this->iconSizeHint($tag);
                $iconLinks[] = ['url' => $resolved, 'size' => $size];
            }
        }

        usort($iconLinks, fn (array $a, array $b) => $b['size'] <=> $a['size']);

        foreach ($iconLinks as $link) {
            $urls[] = $link['url'];
        }

        if ($origin !== null) {
            $urls[] = rtrim($origin, '/').'/favicon.ico';
        }

        return array_values(array_unique($urls));
    }

    private function downloadIcon(string $url): ?string
    {
        $page = $this->detector->fetchPage($url, 0);

        if ($page === null || $page['status'] >= 400) {
            return null;
        }

        $body = $page['body'];

        if ($body === '' || strlen($body) > self::MAX_ICON_BYTES) {
            return null;
        }

        if (! $this->looksLikeImage($body)) {
            return null;
        }

        return $body;
    }

    private function store(Check $check, string $bytes): bool
    {
        $hash = hash('sha256', $bytes);
        $extension = $this->extensionFor($bytes);
        $path = "check-favicons/{$check->id}.{$extension}";

        $previousHash = $check->favicon_hash;

        if ($check->favicon_path !== null && $check->favicon_path !== $path) {
            Storage::disk('local')->delete($check->favicon_path);
        }

        Storage::disk('local')->put($path, $bytes);

        $tags = $check->tags ?? [];

        if ($previousHash !== null && $previousHash !== $hash && ! in_array('favicon-changed', $tags, true)) {
            $tags[] = 'favicon-changed';
        }

        $check->forceFill([
            'favicon_path' => $path,
            'favicon_hash' => $hash,
            'favicon_fetched_at' => now(),
            'tags' => $tags === [] ? null : $tags,
        ])->save();

        return true;
    }

    public function purge(Check $check): void
    {
        if ($check->favicon_path !== null) {
            Storage::disk('local')->delete($check->favicon_path);
        }
    }

    private function looksLikeImage(string $bytes): bool
    {
        if (str_starts_with($bytes, "\x89PNG\r\n\x1a\n")) {
            return true;
        }

        if (str_starts_with($bytes, "\xFF\xD8\xFF")) {
            return true;
        }

        if (str_starts_with($bytes, 'GIF87a') || str_starts_with($bytes, 'GIF89a')) {
            return true;
        }

        if (strlen($bytes) >= 12 && substr($bytes, 0, 4) === 'RIFF' && substr($bytes, 8, 4) === 'WEBP') {
            return true;
        }

        return strlen($bytes) >= 4 && substr($bytes, 0, 4) === "\x00\x00\x01\x00";
    }

    private function extensionFor(string $bytes): string
    {
        return match (true) {
            str_starts_with($bytes, "\x89PNG\r\n\x1a\n") => 'png',
            str_starts_with($bytes, "\xFF\xD8\xFF") => 'jpg',
            str_starts_with($bytes, 'GIF87a'), str_starts_with($bytes, 'GIF89a') => 'gif',
            strlen($bytes) >= 12 && substr($bytes, 0, 4) === 'RIFF' && substr($bytes, 8, 4) === 'WEBP' => 'webp',
            default => 'ico',
        };
    }

    private function attr(string $tag, string $name): ?string
    {
        if (preg_match('/\b'.preg_quote($name, '/').'\s*=\s*["\']([^"\']*)["\']/i', $tag, $m) === 1) {
            return $m[1];
        }

        return null;
    }

    private function iconSizeHint(string $tag): int
    {
        $sizes = $this->attr($tag, 'sizes') ?? '';

        if ($sizes === '' || $sizes === 'any') {
            return str_contains(strtolower($this->attr($tag, 'rel') ?? ''), 'apple-touch') ? 180 : 32;
        }

        $max = 0;

        foreach (preg_split('/\s+/', trim($sizes)) ?: [] as $part) {
            if (preg_match('/^(\d+)x(\d+)$/i', $part, $m) === 1) {
                $max = max($max, (int) $m[1], (int) $m[2]);
            }
        }

        return $max > 0 ? $max : 16;
    }

    private function resolveUrl(string $base, string $href): ?string
    {
        if (preg_match('#^[a-z][a-z0-9+.-]*:#i', $href) === 1) {
            return $href;
        }

        $parts = parse_url($base);

        if ($parts === false || ! isset($parts['scheme'], $parts['host'])) {
            return null;
        }

        $authority = $parts['scheme'].'://'.$parts['host'].(isset($parts['port']) ? ':'.$parts['port'] : '');

        if (str_starts_with($href, '//')) {
            return $parts['scheme'].':'.$href;
        }

        if (str_starts_with($href, '/')) {
            return $authority.$href;
        }

        $path = $parts['path'] ?? '/';
        $dir = str_contains($path, '/') ? substr($path, 0, (int) strrpos($path, '/')) : '';

        return $authority.$dir.'/'.$href;
    }

    private function origin(string $url): ?string
    {
        $parts = parse_url($url);

        if ($parts === false || ! isset($parts['scheme'], $parts['host'])) {
            return null;
        }

        return $parts['scheme'].'://'.$parts['host'].(isset($parts['port']) ? ':'.$parts['port'] : '');
    }
}
