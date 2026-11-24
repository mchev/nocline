<?php

namespace App\Modules\Checks\Console;

use App\Modules\Checks\Services\KumaImporter;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use InvalidArgumentException;

#[Signature('phare:import-kuma {file : Uptime Kuma JSON backup or kuma.db} {--dry-run : Show what would be imported}')]
#[Description('Import Uptime Kuma monitors as checks')]
class ImportKuma extends Command
{
    public function handle(KumaImporter $importer): int
    {
        $file = (string) $this->argument('file');

        if (! is_file($file)) {
            $this->components->error("File not found: {$file}");

            return self::FAILURE;
        }

        try {
            $result = $importer->import($importer->read($file), (bool) $this->option('dry-run'));
        } catch (InvalidArgumentException $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        $verb = $this->option('dry-run') ? 'would be imported' : 'imported';
        $this->components->info(count($result['created'])." monitors {$verb}, ".count($result['skipped']).' skipped.');

        foreach ($result['skipped'] as $s) {
            $this->line("  - {$s['name']}: {$s['reason']}");
        }

        return self::SUCCESS;
    }
}
