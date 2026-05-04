<?php

namespace App\Console\Commands;

use App\Support\CdrEtlState;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class ImportCdr extends Command
{
    protected $signature = 'cdr:import
        {source=all : Source to import: all, mmg, or occ}
        {--stop-on-failure : Stop when an importer returns an error}';

    protected $description = 'Import CDR files for MMG and OCC';

    public function handle(): int
    {
        if (CdrEtlState::isPaused()) {
            $pausedAt = CdrEtlState::pausedAt();
            $message = $pausedAt
                ? "CDR ETL is paused by admin since {$pausedAt}."
                : 'CDR ETL is paused by admin.';

            $this->warn($message);
            return 0;
        }

        $lock = Cache::lock('cdr-import', 3600);

        if (! $lock->get()) {
            $this->warn('CDR import is already running.');
            return 0;
        }

        $startedAt = microtime(true);
        $this->info('CDR import started at: ' . now()->format('Y-m-d H:i:s'));

        try {
            return $this->runImport();
        } finally {
            $this->info('CDR import finished at: ' . now()->format('Y-m-d H:i:s'));
            $this->info('Duration: ' . $this->formatDuration(microtime(true) - $startedAt));
            $lock->release();
        }
    }

    private function runImport(): int
    {
        $source = strtolower((string) $this->argument('source'));
        $commands = $this->commandsForSource($source);

        if ($commands === null) {
            $this->error('Invalid source. Use: all, mmg, or occ.');
            return 1;
        }

        $failed = [];

        foreach ($commands as $label => $command) {
            $this->newLine();
            $this->info("=== {$label} import ===");

            $exitCode = $this->call($command);

            if ($exitCode !== 0) {
                $failed[$label] = $exitCode;

                if ($this->option('stop-on-failure')) {
                    return $exitCode;
                }
            }
        }

        if ($failed !== []) {
            foreach ($failed as $label => $exitCode) {
                $this->error("{$label} import failed with exit code {$exitCode}");
            }

            return 1;
        }

        $this->newLine();
        $this->info('CDR import finished successfully.');

        return 0;
    }

    private function formatDuration(float $seconds): string
    {
        $totalSeconds = (int) round($seconds);
        $hours = intdiv($totalSeconds, 3600);
        $minutes = intdiv($totalSeconds % 3600, 60);
        $remainingSeconds = $totalSeconds % 60;

        return sprintf('%02d:%02d:%02d', $hours, $minutes, $remainingSeconds);
    }

    private function commandsForSource(string $source): ?array
    {
        return match ($source) {
            'all', 'both' => [
                'MMG' => 'cdr:import-mmg',
                'OCC' => 'cdr:import-occ',
            ],
            'mmg' => [
                'MMG' => 'cdr:import-mmg',
            ],
            'occ' => [
                'OCC' => 'cdr:import-occ',
            ],
            default => null,
        };
    }
}
