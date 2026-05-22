<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\CdrFtpClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\View\View;
use Throwable;

class FtpController extends Controller
{
    public function index(CdrFtpClient $client): View
    {
        return view('admin.ftp.index', [
            'activeRole' => User::ROLE_ADMIN,
            'roleLabel' => User::roleLabel(User::ROLE_ADMIN),
            'ftpConfig' => $client->configurationStatus(),
            'localSources' => $this->localSources(),
            'logPreview' => $this->logPreview(),
        ]);
    }

    public function fetch(Request $request, CdrFtpClient $client): RedirectResponse
    {
        $data = $request->validate([
            'source' => ['required', 'in:all,mmg,occ'],
            'overwrite' => ['nullable', 'boolean'],
        ]);

        try {
            $result = $client->fetch($data['source'], (bool) ($data['overwrite'] ?? false));

            return redirect()
                ->route('admin.ftp.index')
                ->with('ftpResult', [
                    'ok' => $result['ok'],
                    'message' => $result['ok']
                        ? 'Synchronisation FTP terminee.'
                        : 'Synchronisation FTP terminee avec des erreurs.',
                    'downloaded' => $result['downloaded'],
                    'skipped' => $result['skipped'],
                    'failed' => $result['failed'],
                    'messages' => $result['messages'],
                ]);
        } catch (Throwable $e) {
            return redirect()
                ->route('admin.ftp.index')
                ->with('ftpResult', [
                    'ok' => false,
                    'message' => 'Synchronisation FTP impossible.',
                    'downloaded' => 0,
                    'skipped' => 0,
                    'failed' => 1,
                    'messages' => [$e->getMessage()],
                ]);
        }
    }

    private function localSources(): array
    {
        return collect(config('cdr_ftp.sources', []))
            ->map(function (array $source, string $key): array {
                $path = storage_path((string) $source['local_path']);

                if (! File::exists($path)) {
                    File::makeDirectory($path, 0755, true);
                }

                $files = collect(File::files($path))
                    ->sortByDesc(fn ($file) => $file->getMTime())
                    ->values();

                return [
                    'key' => $key,
                    'label' => $source['label'] ?? strtoupper($key),
                    'remote_path' => $source['remote_path'] ?? '/',
                    'local_path' => $this->relativePath($path),
                    'count' => $files->count(),
                    'size' => $this->formatBytes((int) $files->sum(fn ($file) => $file->getSize())),
                    'latest' => $files->isEmpty() ? '--' : date('d/m/Y H:i:s', $files->first()->getMTime()),
                    'files' => $files
                        ->take(8)
                        ->map(fn ($file): array => [
                            'name' => $file->getFilename(),
                            'size' => $this->formatBytes($file->getSize()),
                            'modified_at' => date('d/m/Y H:i:s', $file->getMTime()),
                        ])
                        ->all(),
                ];
            })
            ->values()
            ->all();
    }

    private function logPreview(): array
    {
        $path = storage_path('logs/cdr-ftp.log');

        if (! File::exists($path)) {
            return [];
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES) ?: [];

        return array_slice(array_map('trim', $lines), -18);
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
