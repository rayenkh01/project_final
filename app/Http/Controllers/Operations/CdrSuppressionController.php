<?php

namespace App\Http\Controllers\Operations;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\View\View;
use Throwable;

class CdrSuppressionController extends Controller
{
    private const RETENTION_DAYS = 30;

    private const SOURCES = [
        'mmg' => [
            'label' => 'MMG',
            'tmp_table' => 'RA_T_TMP_MMG',
            'processed' => 'app/cdr/processed/mmg',
            'error' => 'app/cdr/error/mmg',
        ],
        'occ' => [
            'label' => 'OCC',
            'tmp_table' => 'RA_T_TMP_OCC',
            'processed' => 'app/cdr/processed/occ',
            'error' => 'app/cdr/error/occ',
        ],
    ];

    public function index(): View
    {
        $cutoff = now()->subDays(self::RETENTION_DAYS);
        $sources = $this->sources($cutoff);

        return view('operations.cdr.suppression', [
            'activeRole' => User::ROLE_OPERATIONAL,
            'roleLabel' => User::roleLabel(User::ROLE_OPERATIONAL),
            'retentionDays' => self::RETENTION_DAYS,
            'cutoff' => $cutoff->format('d/m/Y H:i:s'),
            'sources' => $sources,
            'summary' => $this->summary($sources),
            'cleanupLog' => $this->cleanupLog(),
        ]);
    }

    private function sources(Carbon $cutoff): array
    {
        $sources = [];

        foreach (self::SOURCES as $key => $source) {
            $tmp = $this->tmpCleanupStats($source['tmp_table'], $cutoff);
            $processed = $this->folderCleanupStats(storage_path($source['processed']), $cutoff);
            $error = $this->folderSummary(storage_path($source['error']));

            $sources[$key] = [
                'label' => $source['label'],
                'tmp_table' => $source['tmp_table'],
                'tmp' => $tmp,
                'processed' => $processed,
                'error' => $error,
                'status' => ($tmp['old_rows'] + $processed['old_files']) > 0 ? 'A nettoyer' : 'OK',
            ];
        }

        return $sources;
    }

    private function tmpCleanupStats(string $table, Carbon $cutoff): array
    {
        try {
            $totalRows = (int) DB::table($table)->count();
            $oldFiles = [];
            $oldRows = 0;

            DB::table($table)
                ->select('FILENAME')
                ->whereNotNull('FILENAME')
                ->distinct()
                ->orderBy('FILENAME')
                ->chunk(500, function ($rows) use (&$oldFiles, $cutoff): void {
                    foreach ($rows as $row) {
                        $filename = (string) $this->objectValue($row, 'FILENAME');
                        $importedAt = $this->importDateFromFilename($filename);

                        if ($importedAt !== null && $importedAt->lessThan($cutoff)) {
                            $oldFiles[] = $filename;
                        }
                    }
                });

            foreach (array_chunk($oldFiles, 500) as $chunk) {
                $oldRows += (int) DB::table($table)->whereIn('FILENAME', $chunk)->count();
            }

            return [
                'total_rows' => $totalRows,
                'old_rows' => $oldRows,
                'old_files' => count($oldFiles),
                'error' => null,
            ];
        } catch (Throwable $e) {
            return [
                'total_rows' => 0,
                'old_rows' => 0,
                'old_files' => 0,
                'error' => $e->getMessage(),
            ];
        }
    }

    private function folderCleanupStats(string $path, Carbon $cutoff): array
    {
        $summary = $this->folderSummary($path);

        if (! File::exists($path)) {
            return [...$summary, 'old_files' => 0, 'old_size' => '0 B', 'old_bytes' => 0, 'old_latest' => '--'];
        }

        $oldFiles = collect(File::files($path))
            ->filter(fn ($file): bool => Carbon::createFromTimestamp($file->getMTime())->lessThan($cutoff))
            ->sortByDesc(fn ($file) => $file->getMTime())
            ->values();

        $oldBytes = (int) $oldFiles->sum(fn ($file) => $file->getSize());

        return [
            ...$summary,
            'old_files' => $oldFiles->count(),
            'old_size' => $this->formatBytes($oldBytes),
            'old_bytes' => $oldBytes,
            'old_latest' => $oldFiles->isEmpty() ? '--' : $this->formatDate('@' . $oldFiles->first()->getMTime()),
        ];
    }

    private function folderSummary(string $path): array
    {
        if (! File::exists($path)) {
            return [
                'path' => $this->relativePath($path),
                'files' => 0,
                'size' => '0 B',
                'bytes' => 0,
                'latest' => '--',
            ];
        }

        $files = collect(File::files($path))->sortByDesc(fn ($file) => $file->getMTime())->values();
        $bytes = (int) $files->sum(fn ($file) => $file->getSize());

        return [
            'path' => $this->relativePath($path),
            'files' => $files->count(),
            'size' => $this->formatBytes($bytes),
            'bytes' => $bytes,
            'latest' => $files->isEmpty() ? '--' : $this->formatDate('@' . $files->first()->getMTime()),
        ];
    }

    private function summary(array $sources): array
    {
        $oldRows = 0;
        $oldFiles = 0;
        $oldBytes = 0;
        $errorFiles = 0;

        foreach ($sources as $source) {
            $oldRows += $source['tmp']['old_rows'];
            $oldFiles += $source['processed']['old_files'];
            $oldBytes += $source['processed']['old_bytes'];
            $errorFiles += $source['error']['files'];
        }

        return [
            'old_rows' => $oldRows,
            'old_files' => $oldFiles,
            'old_size' => $this->formatBytes($oldBytes),
            'error_files' => $errorFiles,
        ];
    }

    private function cleanupLog(): array
    {
        $path = storage_path('logs/cdr-cleanup.log');

        if (! File::exists($path)) {
            return [
                'path' => $this->relativePath($path),
                'last_update' => '--',
                'status' => 'En attente',
                'lines' => [],
            ];
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES) ?: [];
        $preview = array_slice(array_map([$this, 'cleanLogLine'], $lines), -18);
        $text = strtolower(implode("\n", $preview));

        return [
            'path' => $this->relativePath($path),
            'last_update' => $this->formatDate('@' . (File::lastModified($path) ?: time())),
            'status' => str_contains($text, 'error') || str_contains($text, 'failed') ? 'Erreur' : 'OK',
            'lines' => $preview,
        ];
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

    private function cleanLogLine(string $line): string
    {
        return trim((string) preg_replace('/\e\[[\d;]*m/', '', $line));
    }

    private function formatDate(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '--';
        }

        try {
            return Carbon::parse((string) $value)->format('d/m/Y H:i:s');
        } catch (Throwable) {
            return (string) $value;
        }
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

    private function relativePath(string $path): string
    {
        return str_replace(base_path() . DIRECTORY_SEPARATOR, '', $path);
    }
}
