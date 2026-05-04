<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Throwable;

class CleanupCdrData extends Command
{
    protected $signature = 'cdr:cleanup
        {--days=30 : Number of days to keep TMP rows and processed files}
        {--dry-run : Show what would be deleted without deleting anything}';

    protected $description = 'Delete old CDR TMP rows and processed files';

    private const TMP_TABLES = [
        'MMG' => 'ra_t_tmp_mmg',
        'OCC' => 'ra_t_tmp_occ',
    ];

    private const PROCESSED_DIRS = [
        'MMG' => 'app/cdr/processed/mmg',
        'OCC' => 'app/cdr/processed/occ',
    ];

    public function handle(): int
    {
        $days = max(1, (int) $this->option('days'));
        $dryRun = (bool) $this->option('dry-run');
        $cutoff = now()->subDays($days);
        $lock = Cache::lock('cdr-import', 3600);

        if (! $lock->get()) {
            $this->warn('CDR import or cleanup is already running.');
            return 0;
        }

        $this->info('CDR cleanup started at: ' . now()->format('Y-m-d H:i:s'));
        $this->info("Retention: {$days} days");
        $this->info('Cutoff: ' . $cutoff->format('Y-m-d H:i:s'));

        if ($dryRun) {
            $this->warn('Dry run enabled: no data will be deleted.');
        }

        try {
            foreach (self::TMP_TABLES as $source => $table) {
                $result = $this->cleanupTmpTable($table, $cutoff, $dryRun);

                $this->info(sprintf(
                    '%s TMP: %d rows %s from %d import file(s).',
                    $source,
                    $result['rows'],
                    $dryRun ? 'matched' : 'deleted',
                    $result['files'],
                ));
            }

            foreach (self::PROCESSED_DIRS as $source => $relativePath) {
                $result = $this->cleanupProcessedDirectory(storage_path($relativePath), $cutoff, $dryRun);

                $this->info(sprintf(
                    '%s processed: %d file(s) %s, %s.',
                    $source,
                    $result['files'],
                    $dryRun ? 'matched' : 'deleted',
                    $this->formatBytes($result['bytes']),
                ));
            }

            $this->info('CDR cleanup finished at: ' . now()->format('Y-m-d H:i:s'));

            return 0;
        } catch (Throwable $e) {
            $this->error('CDR cleanup failed.');
            $this->error($e->getMessage());

            return 1;
        } finally {
            $lock->release();
        }
    }

    private function cleanupTmpTable(string $table, Carbon $cutoff, bool $dryRun): array
    {
        $oldFilenames = [];

        DB::table($table)
            ->select('FILENAME')
            ->whereNotNull('FILENAME')
            ->distinct()
            ->orderBy('FILENAME')
            ->chunk(500, function ($rows) use (&$oldFilenames, $cutoff): void {
                foreach ($rows as $row) {
                    $filename = (string) $this->objectValue($row, 'FILENAME');
                    $importedAt = $this->importDateFromFilename($filename);

                    if ($importedAt !== null && $importedAt->lessThan($cutoff)) {
                        $oldFilenames[] = $filename;
                    }
                }
            });

        if ($oldFilenames === []) {
            return ['files' => 0, 'rows' => 0];
        }

        $rows = 0;

        foreach (array_chunk($oldFilenames, 500) as $chunk) {
            $query = DB::table($table)->whereIn('FILENAME', $chunk);
            $rows += $dryRun ? (int) $query->count() : (int) $query->delete();
        }

        return ['files' => count($oldFilenames), 'rows' => $rows];
    }

    private function cleanupProcessedDirectory(string $path, Carbon $cutoff, bool $dryRun): array
    {
        if (! File::exists($path)) {
            return ['files' => 0, 'bytes' => 0];
        }

        $root = realpath($path);

        if ($root === false) {
            return ['files' => 0, 'bytes' => 0];
        }

        $deletedFiles = 0;
        $deletedBytes = 0;

        foreach (File::allFiles($path) as $file) {
            $filePath = $file->getPathname();
            $realFilePath = realpath($filePath);

            if ($realFilePath === false || ! str_starts_with($realFilePath, $root . DIRECTORY_SEPARATOR)) {
                continue;
            }

            $modifiedAt = Carbon::createFromTimestamp($file->getMTime());

            if ($modifiedAt->greaterThanOrEqualTo($cutoff)) {
                continue;
            }

            $deletedFiles++;
            $deletedBytes += $file->getSize();

            if (! $dryRun) {
                File::delete($realFilePath);
            }
        }

        return ['files' => $deletedFiles, 'bytes' => $deletedBytes];
    }

    private function importDateFromFilename(string $filename): ?Carbon
    {
        if (preg_match('/_([0-9]{14})_[0-9]+(?:\.[^.]+)?$/', $filename, $matches) !== 1) {
            return null;
        }

        try {
            return Carbon::createFromFormat('YmdHis', $matches[1]);
        } catch (Throwable) {
            return null;
        }
    }

    private function objectValue(object $row, string $key): mixed
    {
        foreach ([$key, strtolower($key), strtoupper($key)] as $property) {
            if (property_exists($row, $property)) {
                return $row->{$property};
            }
        }

        return null;
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes . ' B';
        }

        if ($bytes < 1024 * 1024) {
            return number_format($bytes / 1024, 1, ',', ' ') . ' KB';
        }

        return number_format($bytes / (1024 * 1024), 1, ',', ' ') . ' MB';
    }
}
