<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Throwable;

class ImportMmgTmp extends Command
{
    protected $signature = 'cdr:import-mmg';
    protected $description = 'Import MMG CSV files into TMP, DETAIL then AGG';

    private const TMP_COLUMNS = [
        'AGGREGATION_GROUP',
        'APN',
        'A_IMSI',
        'A_MSISDN',
        'A_MSISDN_ORIG',
        'BEARER_SERVICE',
        'B_DATASOURCE',
        'B_IMSI',
        'B_MSISDN',
        'B_MSISDN_ORIG',
        'CALL_REFERENCE',
        'CALL_TYPE',
        'CAUSE_FOR_CLOSING',
        'CDR_SEARCH_DETAIL_ID',
        'CELL_ID',
        'CGI_ID_KEY',
        'CHARGE_AMNT_STEP',
        'CHARGE_AMOUNT_ORIG',
        'C_NUM',
        'C_NUM_ORIG',
        'DATA_VOLUME',
        'DATA_VOLUME_DOWN',
        'DATA_VOLUME_UP',
        'DURATION_STEP',
        'ESTIMATED_AMOUNT',
        'EVENT_DURATION',
        'EVENT_STATUS',
        'EVENT_TYPE',
        'EVENT_TYPE_ORIG',
        'FILENAME',
        'FILTER_CODE',
        'IMEI',
        'LAST_PARTIAL',
        'NE',
        'ORIG_START_TIME',
        'PARTIAL_SEQ_ID',
        'PARTNER',
        'PARTNER_CODE',
        'PGW_ADDRESS',
        'PRICE_PLAN_CODE',
        'PROC_DATE',
        'PROC_HOUR',
        'RADIO_TYPE',
        'RATE_CODE',
        'RECORD_ID',
        'RECORD_STATUS',
        'RECORD_TYPE',
        'ROAMING_TYPE',
        'SERVED_MSRN',
        'SERVICE_ID',
        'SERVICE_PARTNER',
        'SERVICE_TYPE',
        'SGSN_ADDRESS',
        'SMS_CENTRE',
        'START_DATE_TIME_HOME',
        'START_TIME',
        'SUBSCRIBER_TYPE',
        'TELESERVICE',
        'TEST_FLAG',
        'TON_A',
        'TON_B',
        'TON_C',
        'TRAFFIC_TYPE',
        'TRUNK_IN',
        'TRUNK_OUT',
    ];

    public function handle()
    {
        $incomingPath  = storage_path('app/cdr/incoming/mmg');
        $processedPath = storage_path('app/cdr/processed/mmg');
        $errorPath     = storage_path('app/cdr/error/mmg');

        if (!File::exists($incomingPath)) {
            $this->error("Incoming folder not found");
            return 1;
        }

        if (!File::exists($processedPath)) {
            File::makeDirectory($processedPath, 0755, true);
        }

        if (!File::exists($errorPath)) {
            File::makeDirectory($errorPath, 0755, true);
        }

        $files = File::files($incomingPath);

        if (empty($files)) {
            $this->warn('No MMG files found');
            return 0;
        }

        foreach ($files as $file) {

            $fileName = $file->getFilename();
            $filePath = $file->getPathname();
            $importFileName = $this->buildImportFileName($fileName);

            $this->info("Processing: {$fileName}");
            $this->info("Database filename: {$importFileName}");

            $handle = null;

            try {
                $handle = fopen($filePath, 'r');

                if ($handle === false) {
                    throw new \Exception("Cannot open file");
                }

                $this->info('File opened');

                $header = fgetcsv($handle, 0, ',');
                $headerIndexes = $this->buildHeaderIndexes($header);

                $insertedTmp = 0;

                $this->info('Starting DB transaction...');
                DB::beginTransaction();

                $this->info('Preparing TMP insert...');
                $insertTmp = DB::connection()->getPdo()->prepare($this->tmpInsertSql());
                $this->info('Importing TMP rows...');

                /**
                 * 1) TMP
                 */
                while (($row = fgetcsv($handle, 0, ',')) !== false) {

                    if ($this->isBlankCsvRow($row)) continue;

                    $insertTmp->execute($this->tmpInsertValues(
                        $this->mapTmpRow($row, $headerIndexes, $importFileName)
                    ));
                    $insertedTmp++;

                    if ($insertedTmp % 1000 === 0) {
                        $this->info("TMP rows: {$insertedTmp}");
                    }
                }

                fclose($handle);
                $handle = null;

                $this->info("TMP rows: {$insertedTmp}");

                /**
                 * 2) DETAIL
                 */
                $this->info('Building DETAIL rows...');

                $insertedDetail = DB::affectingStatement("
                    INSERT INTO ra_t_detail_mmg (
                        A_MSISDN,
                        B_MSISDN,
                        CALL_TYPE,
                        EVENT_STATUS,
                        EVENT_TYPE,
                        EVENT_TYPE_ORIG,
                        FILENAME,
                        NE,
                        ORIG_START_TIME,
                        SERVICE_TYPE,
                        SUBSCRIBER_TYPE,
                        TRAFFIC_TYPE,
                        START_DATE,
                        START_HOUR,
                        CREATED_AT
                    )
                    SELECT
                        TRIM(A_MSISDN),
                        TRIM(B_MSISDN),
                        TRIM(CALL_TYPE),
                        TRIM(EVENT_STATUS),
                        TRIM(EVENT_TYPE),
                        TRIM(EVENT_TYPE_ORIG),
                        FILENAME,
                        TRIM(NE),
                        TRIM(ORIG_START_TIME),
                        TRIM(SERVICE_TYPE),
                        TRIM(SUBSCRIBER_TYPE),
                        TRIM(TRAFFIC_TYPE),
                        CASE
                            WHEN REGEXP_LIKE(TRIM(ORIG_START_TIME), '^[0-9]{14}$')
                                THEN TRUNC(TO_DATE(TRIM(ORIG_START_TIME), 'YYYYMMDDHH24MISS'))
                            WHEN REGEXP_LIKE(TRIM(PROC_DATE), '^[0-9]{8}')
                                THEN TO_DATE(SUBSTR(TRIM(PROC_DATE), 1, 8), 'YYYYMMDD')
                        END,
                        CASE
                            WHEN REGEXP_LIKE(TRIM(ORIG_START_TIME), '^[0-9]{10,}$')
                                THEN SUBSTR(TRIM(ORIG_START_TIME), 9, 2)
                            WHEN REGEXP_LIKE(TRIM(PROC_HOUR), '^[0-9]{1,2}$')
                                THEN LPAD(TRIM(PROC_HOUR), 2, '0')
                        END,
                        SYSDATE
                    FROM ra_t_tmp_mmg
                    WHERE FILENAME = ?
                ", [$importFileName]);

                $this->info("DETAIL rows: {$insertedDetail}");

                /**
                 * 3) AGG
                 */
                $this->info('Building AGG rows...');

                $insertedAgg = DB::affectingStatement("
                    INSERT INTO ra_t_agg_mmg (
                        B_MSISDN,
                        START_DATE,
                        START_HOUR,
                        EVENT_TYPE,
                        CALL_TYPE,
                        EVENT_STATUS,
                        SUBSCRIBER_TYPE,
                        SERVICE_TYPE,
                        CDR_COUNT,
                        FILENAME,
                        CREATED_AT
                    )
                    SELECT
                        B_MSISDN,
                        START_DATE,
                        CASE
                            WHEN REGEXP_LIKE(TRIM(START_HOUR), '^[0-9]{1,2}$')
                                THEN TO_NUMBER(TRIM(START_HOUR))
                        END,
                        EVENT_TYPE,
                        CALL_TYPE,
                        EVENT_STATUS,
                        SUBSCRIBER_TYPE,
                        SERVICE_TYPE,
                        COUNT(*),
                        FILENAME,
                        SYSDATE
                    FROM ra_t_detail_mmg
                    WHERE FILENAME = ?
                    GROUP BY
                        B_MSISDN,
                        START_DATE,
                        CASE
                            WHEN REGEXP_LIKE(TRIM(START_HOUR), '^[0-9]{1,2}$')
                                THEN TO_NUMBER(TRIM(START_HOUR))
                        END,
                        EVENT_TYPE,
                        CALL_TYPE,
                        EVENT_STATUS,
                        SUBSCRIBER_TYPE,
                        SERVICE_TYPE,
                        FILENAME
                ", [$importFileName]);

                $this->info("AGG rows: {$insertedAgg}");

                DB::commit();

                /**
                 * 4) MOVE FILE
                 */
                $processedFilePath = $processedPath . DIRECTORY_SEPARATOR . $fileName;
                File::move($filePath, $processedFilePath);
                touch($processedFilePath);

                $this->info("SUCCESS: {$fileName}");

            } catch (Throwable $e) {

                if (DB::transactionLevel() > 0) {
                    DB::rollBack();
                }

                if (is_resource($handle)) {
                    fclose($handle);
                }

                if (File::exists($filePath)) {
                    File::move($filePath, $errorPath . DIRECTORY_SEPARATOR . $fileName);
                }

                $this->error("FAILED: {$fileName}");
                $this->error($e->getMessage());
            }
        }

        return 0;
    }

    private function buildHeaderIndexes(array|false|null $header): array
    {
        $indexes = [];

        foreach (self::TMP_COLUMNS as $index => $column) {
            $indexes[$column] = $index;
        }

        if ($header === false || $header === null) {
            return $indexes;
        }

        foreach ($header as $index => $column) {
            $column = strtoupper(trim((string) $column));

            if (in_array($column, self::TMP_COLUMNS, true)) {
                $indexes[$column] = $index;
            }
        }

        return $indexes;
    }

    private function mapTmpRow(array $row, array $headerIndexes, string $fileName): array
    {
        $mapped = [];

        foreach (self::TMP_COLUMNS as $column) {
            $mapped[$column] = $column === 'FILENAME'
                ? $fileName
                : $this->cleanValue($row[$headerIndexes[$column]] ?? null);
        }

        return $mapped;
    }

    private function buildImportFileName(string $fileName): string
    {
        $name = pathinfo($fileName, PATHINFO_FILENAME);
        $extension = pathinfo($fileName, PATHINFO_EXTENSION);
        $suffix = '_' . date('YmdHis') . '_' . getmypid();
        $tail = $extension === '' ? $suffix : $suffix . '.' . $extension;
        $maxNameLength = max(1, 255 - strlen($tail));

        return substr($name, 0, $maxNameLength) . $tail;
    }

    private function tmpInsertSql(): string
    {
        $columns = implode(', ', self::TMP_COLUMNS);
        $placeholders = implode(', ', array_map(
            fn (int $index) => ':p' . $index,
            array_keys(self::TMP_COLUMNS)
        ));

        return "INSERT INTO ra_t_tmp_mmg ({$columns}) VALUES ({$placeholders})";
    }

    private function tmpInsertValues(array $mapped): array
    {
        $values = [];

        foreach (self::TMP_COLUMNS as $index => $column) {
            $values['p' . $index] = $mapped[$column] ?? null;
        }

        return $values;
    }

    private function isBlankCsvRow(array $row): bool
    {
        foreach ($row as $value) {
            if (trim((string) $value) !== '') {
                return false;
            }
        }

        return true;
    }

    private function cleanValue(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
