<?php

namespace App\Support;

use Illuminate\Support\Facades\File;

class CdrEtlState
{
    public static function isPaused(): bool
    {
        return File::exists(self::pauseFilePath());
    }

    public static function pause(): void
    {
        $directory = dirname(self::pauseFilePath());

        if (! File::exists($directory)) {
            File::makeDirectory($directory, 0755, true);
        }

        File::put(self::pauseFilePath(), now()->format('Y-m-d H:i:s'));
    }

    public static function resume(): void
    {
        if (self::isPaused()) {
            File::delete(self::pauseFilePath());
        }
    }

    public static function pausedAt(): ?string
    {
        if (! self::isPaused()) {
            return null;
        }

        $value = trim((string) File::get(self::pauseFilePath()));

        return $value !== '' ? $value : null;
    }

    public static function pauseFilePath(): string
    {
        return storage_path('app/cdr/etl-paused.flag');
    }
}
