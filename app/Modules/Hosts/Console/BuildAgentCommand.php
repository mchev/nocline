<?php

namespace App\Modules\Hosts\Console;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

#[Signature('phare:agent:build')]
#[Description('Build Linux phare-agent binaries locally (maintainers: before tagging, or use release.yml on push tag)')]
class BuildAgentCommand extends Command
{
    public function handle(): int
    {
        $script = base_path('scripts/build-agent.sh');

        if (! is_file($script)) {
            $this->components->error('scripts/build-agent.sh is missing.');

            return self::FAILURE;
        }

        $process = Process::fromShellCommandline('bash '.escapeshellarg($script), base_path());
        $process->setTimeout(600);
        $process->run(function (string $type, string $buffer): void {
            $this->output->write($buffer);
        });

        if (! $process->isSuccessful()) {
            $this->newLine();
            $this->components->error('Agent build failed. Is Go installed? See agent/README.md.');

            return self::FAILURE;
        }

        $dir = config('phare.agent_binary_dir');
        $this->components->info("Agent binaries are in {$dir}");

        return self::SUCCESS;
    }
}
