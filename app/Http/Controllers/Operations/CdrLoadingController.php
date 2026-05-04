<?php

namespace App\Http\Controllers\Operations;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\View\View;
use Throwable;

class CdrLoadingController extends Controller
{
    private const SOURCES = [
        'mmg' => [
            'label' => 'MMG',
            'incoming' => 'app/cdr/incoming/mmg',
            'processed' => 'app/cdr/processed/mmg',
            'error' => 'app/cdr/error/mmg',
            'tables' => [
                ['name' => 'RA_T_TMP_MMG', 'stage' => 'TEMP', 'date_column' => 'FILENAME'],
                ['name' => 'RA_T_DETAIL_MMG', 'stage' => 'DETAIL', 'date_column' => 'CREATED_AT'],
                ['name' => 'RA_T_AGG_MMG', 'stage' => 'AGG', 'date_column' => 'CREATED_AT'],
            ],
        ],
        'occ' => [
            'label' => 'OCC',
            'incoming' => 'app/cdr/incoming/occ',
            'processed' => 'app/cdr/processed/occ',
            'error' => 'app/cdr/error/occ',
            'tables' => [
                ['name' => 'RA_T_TMP_OCC', 'stage' => 'TEMP', 'date_column' => 'FILENAME'],
                ['name' => 'RA_T_DETAIL_OCC', 'stage' => 'DETAIL', 'date_column' => 'CREATED_AT'],
                ['name' => 'RA_T_AGG_OCC', 'stage' => 'AGG', 'date_column' => 'CREATED_AT'],
            ],
        ],
    ];

    public function index(): View
    {
        return view('operations.cdr.loading', [
            'activeRole' => User::ROLE_OPERATIONAL,
            'roleLabel' => User::roleLabel(User::ROLE_OPERATIONAL),
            'sources' => $this->sources(),
            'orphanFiles' => $this->orphanIncomingFiles(),
            'logPreview' => $this->logPreview(),
        ]);
    }

    private function sources(): array
    {
        $sources = [];

        foreach (self::SOURCES as $key => $source) {
            $incoming = $this->folderSummary(storage_path($source['incoming']));
            $processed = $this->folderSummary(storage_path($source['processed']));
            $error = $this->folderSummary(storage_path($source['error']));

            $sources[$key] = [
                'key' => $key,
                'label' => $source['label'],
                'incoming' => $incoming,
                'processed' => $processed,
                'error' => $error,
                'tables' => $this->tableStats($source['tables']),
                'total_files' => $incoming['count'] + $processed['count'] + $error['count'],
                'incoming_count' => $incoming['count'],
                'processed_count' => $processed['count'],
                'error_count' => $error['count'],
            ];
        }

        return $sources;
    }

    private function tableStats(array $tables): array
    {
        return array_map(function (array $table): array {
            try {
                $count = (int) DB::table($table['name'])->count();
                $lastUpdate = $table['date_column'] === 'FILENAME'
                    ? $this->lastTmpImportDate($table['name'])
                    : DB::table($table['name'])->max($table['date_column']);

                return [
                    ...$table,
                    'records' => $this->formatNumber($count),
                    'last_update' => $this->formatDate($lastUpdate),
                    'status' => 'ok',
                ];
            } catch (Throwable) {
                return [
                    ...$table,
                    'records' => '--',
                    'last_update' => '--',
                    'status' => 'error',
                ];
            }
        }, $tables);
    }

    private function folderSummary(string $path): array
    {
        $this->ensureDirectory($path);

        $files = collect(File::files($path))
            ->sortByDesc(fn ($file) => $file->getMTime())
            ->values();

        $size = $files->sum(fn ($file) => $file->getSize());

        return [
            'path' => $this->relativePath($path),
            'count' => $files->count(),
            'size' => $this->formatBytes((int) $size),
            'latest' => $files->isEmpty() ? '--' : $this->formatDate('@' . $files->first()->getMTime()),
            'files' => $files
                ->take(6)
                ->map(fn ($file): array => [
                    'name' => $file->getFilename(),
                    'size' => $this->formatBytes($file->getSize()),
                    'modified_at' => $this->formatDate('@' . $file->getMTime()),
                ])
                ->all(),
        ];
    }

    private function orphanIncomingFiles(): array
    {
        $incomingRoot = storage_path('app/cdr/incoming');

        $this->ensureDirectory($incomingRoot);

        return collect(File::files($incomingRoot))
            ->sortByDesc(fn ($file) => $file->getMTime())
            ->take(8)
            ->map(fn ($file): array => [
                'name' => $file->getFilename(),
                'size' => $this->formatBytes($file->getSize()),
                'modified_at' => $this->formatDate('@' . $file->getMTime()),
            ])
            ->all();
    }

    private function lastTmpImportDate(string $table): mixed
    {
        $latest = null;

        DB::table($table)
            ->select('FILENAME')
            ->whereNotNull('FILENAME')
            ->distinct()
            ->orderBy('FILENAME')
            ->chunk(500, function ($rows) use (&$latest): void {
                foreach ($rows as $row) {
                    $date = $this->importDateFromFilename((string) $this->objectValue($row, 'FILENAME'));

                    if ($date !== null && ($latest === null || $date->greaterThan($latest))) {
                        $latest = $date;
                    }
                }
            });

        return $latest;
    }

    private function logPreview(): array
    {
        $path = storage_path('logs/cdr-import.log');

        if (! File::exists($path)) {
            return [];
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES) ?: [];

        return array_map(
            fn (string $line): string => trim((string) preg_replace('/\e\[[\d;]*m/', '', $line)),
            array_slice($lines, -18)
        );
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

    private function ensureDirectory(string $path): void
    {
        if (! File::exists($path)) {
            File::makeDirectory($path, 0755, true);
        }
    }

    private function formatDate(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '--';
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format('d/m/Y H:i:s');
        }

        try {
            return Carbon::parse((string) $value)->format('d/m/Y H:i:s');
        } catch (Throwable) {
            return (string) $value;
        }
    }

    private function formatNumber(int|float $value): string
    {
        return number_format((float) $value, 0, ',', ' ');
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
