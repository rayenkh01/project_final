<?php

namespace App\Console\Commands;

use App\Services\CdrFtpClient;
use Illuminate\Console\Command;
use Throwable;

class FetchCdrFromFtp extends Command
{
    protected $signature = 'cdr:ftp-fetch
        {source=all : Source to fetch: all, mmg, or occ}
        {--overwrite : Replace local files if they already exist}';

    protected $description = 'Download CDR files from FTP into local incoming folders';

    public function handle(CdrFtpClient $client): int
    {
        try {
            $result = $client->fetch(
                strtolower((string) $this->argument('source')),
                (bool) $this->option('overwrite')
            );
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return 1;
        }

        foreach ($result['messages'] as $message) {
            $this->line($message);
        }

        $this->info("Downloaded: {$result['downloaded']}, skipped: {$result['skipped']}, failed: {$result['failed']}");

        return $result['ok'] ? 0 : 1;
    }
}
