<?php

namespace App\Console\Commands;

use App\Support\Locales;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Symfony\Component\Finder\Finder;

#[Signature('phare:lang:missing {locale? : Check one locale (default: all)} {--fix : Add missing keys to the file with an empty value}')]
#[Description('List UI strings used in code (t(), $t(), __()) that are missing from lang/<locale>.json')]
class LangMissing extends Command
{
    public function handle(): int
    {
        $used = $this->usedKeys();
        $locales = array_keys(Locales::available());
        $target = $this->argument('locale');
        $missingAny = false;

        foreach ($target ? [$target] : array_diff($locales, [Locales::DEFAULT]) as $locale) {
            if (! in_array($locale, $locales, true)) {
                $this->components->error("Unknown locale {$locale}");

                return self::FAILURE;
            }

            $file = lang_path("{$locale}.json");
            $current = Locales::translations($locale);
            $missing = array_values(array_filter($used, fn (string $k) => ($current[$k] ?? '') === ''));
            $this->components->info("{$locale}: ".count($missing).' missing / '.count($used).' used');

            foreach ($missing as $key) {
                $this->line("  - {$key}");
            }

            if ($missing !== [] && $this->option('fix')) {
                foreach ($missing as $key) {
                    $current[$key] = '';
                }
                File::put($file, json_encode($current, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n");
            }

            $missingAny = $missingAny || $missing !== [];
        }

        return $missingAny && ! $this->option('fix') ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @return array<int, string>
     */
    private function usedKeys(): array
    {
        $keys = [];
        $finder = Finder::create()->files()->in([base_path('resources/js'), base_path('app'), base_path('resources/views')])
            ->name(['*.vue', '*.ts', '*.php'])->notPath(['actions', 'routes', 'wayfinder']);

        foreach ($finder as $file) {
            preg_match_all('/(?<![\w.])(?:\$t|t|__)\(\s*(\'((?:[^\'\\\\]|\\\\.)*)\'|"((?:[^"\\\\]|\\\\.)*)")/', $file->getContents(), $m, PREG_SET_ORDER);

            foreach ($m as $match) {
                $keys[] = stripcslashes(($match[2] ?? '') !== '' ? $match[2] : ($match[3] ?? ''));
            }
        }

        foreach (Finder::create()->files()->in(base_path('resources/js/pages'))->name('*.vue') as $file) {
            preg_match_all('/\b(?:title|description):\s*\'((?:[^\'\\\\]|\\\\.)*)\'/', $file->getContents(), $m);
            array_push($keys, ...array_map('stripcslashes', $m[1]));
        }

        $keys = array_values(array_unique(array_filter($keys, fn (string $k) => $k !== '' && $k !== '_language')));
        sort($keys);

        return $keys;
    }
}
