<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\CdrEtlState;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\View\View;
use Throwable;

class DatabaseController extends Controller
{
    private const TABLES = [
        ['name' => 'RA_T_TMP_MMG', 'source' => 'MMG', 'stage' => 'TEMP', 'date_column' => 'PROC_DATE'],
        ['name' => 'RA_T_DETAIL_MMG', 'source' => 'MMG', 'stage' => 'DETAIL', 'date_column' => 'CREATED_AT'],
        ['name' => 'RA_T_AGG_MMG', 'source' => 'MMG', 'stage' => 'AGG', 'date_column' => 'CREATED_AT'],
        ['name' => 'RA_T_TMP_OCC', 'source' => 'OCC', 'stage' => 'TEMP', 'date_column' => 'PROC_DATE'],
        ['name' => 'RA_T_DETAIL_OCC', 'source' => 'OCC', 'stage' => 'DETAIL', 'date_column' => 'CREATED_AT'],
        ['name' => 'RA_T_AGG_OCC', 'source' => 'OCC', 'stage' => 'AGG', 'date_column' => 'CREATED_AT'],
    ];

    public function index(): View
    {
        $log = $this->schedulerLog();

        return view('admin.database.index', [
            'activeRole' => User::ROLE_ADMIN,
            'roleLabel' => User::roleLabel(User::ROLE_ADMIN),
            'scheduler' => $this->schedulerDetails($log),
            'summary' => $this->summary($log),
            'tableStats' => $this->tableStats(),
            'folderStats' => $this->folderStats(),
            'executions' => $this->recentExecutions(),
            'etlState' => $this->etlState(),
            'logPreview' => $log['preview'],
            'databaseError' => null,
        ]);
    }

    public function runImport(): RedirectResponse
    {
        @set_time_limit(0);

        try {
            $exitCode = Artisan::call('cdr:import', [
                'source' => 'all',
                '--stop-on-failure' => true,
            ]);
            $output = trim(Artisan::output());
            $this->appendImportLog($output, $exitCode);

            return redirect()
                ->route('admin.database.index')
                ->with('operationResult', [
                    'ok' => $exitCode === 0,
                    'message' => $exitCode === 0
                        ? 'Import CDR termine avec succes.'
                        : "Import CDR termine avec code {$exitCode}.",
                    'output' => $output,
                ]);
        } catch (Throwable $e) {
            $this->appendImportLog($e->getMessage(), 1);

            return redirect()
                ->route('admin.database.index')
                ->with('operationResult', [
                    'ok' => false,
                    'message' => 'Import CDR impossible.',
                    'output' => $e->getMessage(),
                ]);
        }
    }

    public function toggleEtl(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'action' => ['required', 'in:pause,resume'],
        ]);

        if ($data['action'] === 'pause') {
            CdrEtlState::pause();
            $message = 'ETL mis en pause. Les prochains imports automatiques seront ignores.';
            $this->appendImportLog($message, 0);
        } else {
            CdrEtlState::resume();
            $message = 'ETL demarre. Le Scheduler relancera les imports selon la planification.';
            $this->appendImportLog($message, 0);
        }

        return redirect()
            ->route('admin.database.index')
            ->with('operationResult', [
                'ok' => true,
                'message' => $message,
                'output' => '',
            ]);
    }

    public function cleanupOldData(): RedirectResponse
    {
        @set_time_limit(0);

        try {
            $exitCode = Artisan::call('cdr:cleanup', [
                '--days' => 30,
            ]);
            $output = trim(Artisan::output());
            $this->appendCleanupLog($output, $exitCode);

            return redirect()
                ->route('admin.database.index')
                ->with('operationResult', [
                    'ok' => $exitCode === 0,
                    'message' => $exitCode === 0
                        ? 'Nettoyage des anciennes donnees termine avec succes.'
                        : "Nettoyage termine avec code {$exitCode}.",
                    'output' => $output,
                ]);
        } catch (Throwable $e) {
            $this->appendCleanupLog($e->getMessage(), 1);

            return redirect()
                ->route('admin.database.index')
                ->with('operationResult', [
                    'ok' => false,
                    'message' => 'Nettoyage des anciennes donnees impossible.',
                    'output' => $e->getMessage(),
                ]);
        }
    }

    private function appendImportLog(string $output, int $exitCode): void
    {
        $logDirectory = storage_path('logs');

        if (! File::exists($logDirectory)) {
            File::makeDirectory($logDirectory, 0755, true);
        }

        $status = $exitCode === 0 ? 'SUCCESS' : 'ERROR';
        $content = PHP_EOL
            . "[manual web import] {$status} at " . now()->format('Y-m-d H:i:s') . PHP_EOL
            . ($output !== '' ? $output . PHP_EOL : '')
            . "Manual import exit code: {$exitCode}" . PHP_EOL;

        File::append(storage_path('logs/cdr-import.log'), $content);
    }

    private function appendCleanupLog(string $output, int $exitCode): void
    {
        $logDirectory = storage_path('logs');

        if (! File::exists($logDirectory)) {
            File::makeDirectory($logDirectory, 0755, true);
        }

        $status = $exitCode === 0 ? 'SUCCESS' : 'ERROR';
        $content = PHP_EOL
            . "[manual web cleanup] {$status} at " . now()->format('Y-m-d H:i:s') . PHP_EOL
            . ($output !== '' ? $output . PHP_EOL : '')
            . "Manual cleanup exit code: {$exitCode}" . PHP_EOL;

        File::append(storage_path('logs/cdr-cleanup.log'), $content);
    }

    private function schedulerDetails(array $log): array
    {
        return [
            'status' => 'Configure',
            'frequency' => 'Chaque 15 minutes',
            'command' => 'php artisan cdr:import all --stop-on-failure',
            'cleanup_frequency' => 'Chaque jour a 02:30',
            'cleanup_command' => 'php artisan cdr:cleanup --days=30',
            'overlap' => 'Sans chevauchement: 60 minutes',
            'server_cron' => 'php artisan schedule:run chaque minute',
            'log_path' => 'storage/logs/cdr-import.log',
            'cleanup_log_path' => 'storage/logs/cdr-cleanup.log',
            'last_run_at' => $log['last_run_at'],
            'duration' => $log['duration'],
        ];
    }

    private function etlState(): array
    {
        $paused = CdrEtlState::isPaused();

        return [
            'paused' => $paused,
            'status' => $paused ? 'En pause' : 'Actif',
            'badge' => $paused ? 'text-bg-warning' : 'text-bg-success',
            'paused_at' => CdrEtlState::pausedAt(),
            'flag_path' => $this->relativePath(CdrEtlState::pauseFilePath()),
        ];
    }

    private function summary(array $log): array
    {
        return [
            [
                'label' => 'Derniere execution',
                'value' => $log['last_run_at'],
                'icon' => 'bi-clock-history',
                'tone' => 'blue',
            ],
            [
                'label' => 'Statut',
                'value' => $log['status'],
                'icon' => $log['status'] === 'Erreur' ? 'bi-x-circle' : 'bi-check-circle',
                'tone' => $log['status'] === 'Erreur' ? 'red' : 'green',
            ],
            [
                'label' => 'Duree',
                'value' => $log['duration'],
                'icon' => 'bi-stopwatch',
                'tone' => 'amber',
            ],
            [
                'label' => 'Erreurs log',
                'value' => (string) $log['error_count'],
                'icon' => 'bi-bug',
                'tone' => $log['error_count'] > 0 ? 'red' : 'green',
            ],
        ];
    }

    private function tableStats(): array
    {
        return array_map(function (array $table): array {
            try {
                $count = DB::table($table['name'])->count();
                $lastUpdate = DB::table($table['name'])->max($table['date_column']);

                return [
                    ...$table,
                    'records' => $this->formatNumber($count),
                    'last_update' => $this->formatDate($lastUpdate),
                    'status' => 'ok',
                    'error' => null,
                ];
            } catch (Throwable $e) {
                return [
                    ...$table,
                    'records' => '--',
                    'last_update' => '--',
                    'status' => 'error',
                    'error' => $e->getMessage(),
                ];
            }
        }, self::TABLES);
    }

    private function folderStats(): array
    {
        $folders = [
            ['label' => 'Incoming MMG', 'path' => storage_path('app/cdr/incoming/mmg')],
            ['label' => 'Incoming OCC', 'path' => storage_path('app/cdr/incoming/occ')],
            ['label' => 'Processed MMG', 'path' => storage_path('app/cdr/processed/mmg')],
            ['label' => 'Processed OCC', 'path' => storage_path('app/cdr/processed/occ')],
            ['label' => 'Error MMG', 'path' => storage_path('app/cdr/error/mmg')],
            ['label' => 'Error OCC', 'path' => storage_path('app/cdr/error/occ')],
        ];

        return array_map(function (array $folder): array {
            if (! File::exists($folder['path'])) {
                return [
                    'label' => $folder['label'],
                    'path' => $this->relativePath($folder['path']),
                    'files' => 0,
                    'size' => '0 B',
                ];
            }

            $files = File::files($folder['path']);
            $size = array_reduce($files, fn (int $carry, mixed $file): int => $carry + $file->getSize(), 0);

            return [
                'label' => $folder['label'],
                'path' => $this->relativePath($folder['path']),
                'files' => count($files),
                'size' => $this->formatBytes($size),
            ];
        }, $folders);
    }

    private function recentExecutions(): array
    {
        $executions = [];

        foreach ([
            ['source' => 'MMG', 'table' => 'RA_T_DETAIL_MMG'],
            ['source' => 'OCC', 'table' => 'RA_T_DETAIL_OCC'],
        ] as $source) {
            try {
                $rows = DB::table($source['table'])
                    ->select('FILENAME', DB::raw('COUNT(*) AS TOTAL_ROWS'), DB::raw('MAX(CREATED_AT) AS LAST_UPDATE'))
                    ->groupBy('FILENAME')
                    ->orderByRaw('MAX(CREATED_AT) DESC')
                    ->limit(6)
                    ->get();

                foreach ($rows as $row) {
                    $lastUpdate = $this->objectValue($row, 'LAST_UPDATE');

                    $executions[] = [
                        'date' => $this->formatDate($lastUpdate),
                        'sort_key' => $this->timestamp($lastUpdate),
                        'process' => 'ELT ' . $source['source'],
                        'status' => 'Succes',
                        'duration' => '--',
                        'files' => 1,
                        'records' => $this->formatNumber((int) $this->objectValue($row, 'TOTAL_ROWS')),
                        'errors' => 0,
                        'file' => (string) $this->objectValue($row, 'FILENAME'),
                    ];
                }
            } catch (Throwable) {
                continue;
            }
        }

        usort($executions, fn (array $a, array $b): int => $b['sort_key'] <=> $a['sort_key']);

        return array_slice($executions, 0, 8);
    }

    private function schedulerLog(): array
    {
        $path = storage_path('logs/cdr-import.log');

        if (! File::exists($path)) {
            return [
                'status' => 'En attente',
                'last_run_at' => '--',
                'duration' => '--',
                'error_count' => 0,
                'preview' => [],
            ];
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES) ?: [];
        $lines = array_slice($lines, -220);
        $latestBlock = $this->latestLogBlock($lines);
        $blockText = strtolower(implode("\n", $latestBlock));
        $hasError = str_contains($blockText, 'failed')
            || str_contains($blockText, 'erreur')
            || str_contains($blockText, 'error');

        return [
            'status' => $hasError ? 'Erreur' : 'Succes',
            'last_run_at' => date('d/m/Y H:i:s', filemtime($path) ?: time()),
            'duration' => $this->lastDuration($latestBlock),
            'error_count' => $this->errorCount($latestBlock),
            'preview' => array_slice(array_map([$this, 'cleanLogLine'], $latestBlock), -18),
        ];
    }

    private function latestLogBlock(array $lines): array
    {
        $startIndex = null;

        foreach ($lines as $index => $line) {
            if (str_contains($line, 'CDR import started at:')) {
                $startIndex = $index;
            }
        }

        return $startIndex === null ? $lines : array_slice($lines, $startIndex);
    }

    private function lastDuration(array $lines): string
    {
        foreach (array_reverse($lines) as $line) {
            if (preg_match('/Duration:\s*(.+)$/', $line, $matches) === 1) {
                return trim($matches[1]);
            }
        }

        return '--';
    }

    private function errorCount(array $lines): int
    {
        return count(array_filter($lines, function (string $line): bool {
            return preg_match('/failed|erreur|error/i', $line) === 1;
        }));
    }

    private function cleanLogLine(string $line): string
    {
        return trim((string) preg_replace('/\e\[[\d;]*m/', '', $line));
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

    private function formatDate(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '--';
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format('d/m/Y H:i:s');
        }

        $value = trim((string) $value);

        try {
            if (preg_match('/^\d{14}$/', $value) === 1) {
                return Carbon::createFromFormat('YmdHis', $value)->format('d/m/Y H:i:s');
            }

            if (preg_match('/^\d{8}$/', $value) === 1) {
                return Carbon::createFromFormat('Ymd', $value)->format('d/m/Y');
            }

            return Carbon::parse($value)->format('d/m/Y H:i:s');
        } catch (Throwable) {
            return $value;
        }
    }

    private function timestamp(mixed $value): int
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->getTimestamp();
        }

        try {
            return Carbon::parse((string) $value)->getTimestamp();
        } catch (Throwable) {
            return 0;
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
