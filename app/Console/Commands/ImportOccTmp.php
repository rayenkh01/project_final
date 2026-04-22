<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Throwable;

class ImportOccTmp extends Command
{
    protected $signature = 'cdr:import-occ';
    protected $description = 'Import OCC CSV files into TMP then DETAIL';

    public function handle()
    {
        $incomingPath  = storage_path('app/cdr/incoming/occ');
        $processedPath = storage_path('app/cdr/processed/occ');
        $errorPath     = storage_path('app/cdr/error/occ');

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
            $this->warn('No OCC files found');
            return 0;
        }

        foreach ($files as $file) {

            $fileName = $file->getFilename();
            $filePath = $file->getPathname();

            $this->info("Processing: {$fileName}");

            $handle = null;

            try {
                $handle = fopen($filePath, 'r');

                if ($handle === false) {
                    throw new \Exception("Cannot open file");
                }

                // Skip header
                $header = fgetcsv($handle, 0, ',');

                $batch = [];
                $batchSize = 500;
                $insertedTmp = 0;

                DB::beginTransaction();

                /**
                 * 1) TMP
                 */
                while (($row = fgetcsv($handle, 0, ',')) !== false) {

                    if (count(array_filter($row)) === 0) continue;

                    $batch[] = [
                        'AGGREGATION_GROUP'  => $row[0] ?? null,
                        'APN'                => $row[1] ?? null,
                        'A_IMSI'             => $row[2] ?? null,
                        'A_MSISDN'           => $row[3] ?? null,
                        'A_MSISDN_ORIG'      => $row[4] ?? null,
                        'BEARER_SERVICE'     => $row[5] ?? null,
                        'B_DATASOURCE'       => $row[6] ?? null,
                        'B_IMSI'             => $row[7] ?? null,
                        'B_MSISDN'           => $row[8] ?? null,
                        'B_MSISDN_ORIG'      => $row[9] ?? null,
                        'CALL_REFERENCE'     => $row[10] ?? null,
                        'CALL_TYPE'          => $row[11] ?? null,
                        'CAUSE_FOR_CLOSING'  => $row[12] ?? null,
                        'CDR_SEARCH_DETAIL_ID'=> $row[13] ?? null,
                        'CELL_ID'            => $row[14] ?? null,
                        'CGI_ID_KEY'         => $row[15] ?? null,
                        'CHARGE_AMNT_STEP'   => $row[16] ?? null,
                        'CHARGE_AMOUNT_ORIG' => $row[17] ?? null,
                        'C_NUM'              => $row[18] ?? null,
                        'C_NUM_ORIG'         => $row[19] ?? null,
                        'DATA_VOLUME'        => $row[20] ?? null,
                        'DATA_VOLUME_DOWN'   => $row[21] ?? null,
                        'DATA_VOLUME_UP'     => $row[22] ?? null,
                        'DURATION_STEP'      => $row[23] ?? null,
                        'ESTIMATED_AMOUNT'   => $row[24] ?? null,
                        'EVENT_DURATION'     => $row[25] ?? null,
                        'EVENT_STATUS'       => $row[26] ?? null,
                        'EVENT_TYPE'         => $row[27] ?? null,

                        // 🔥 مهم
                        'FILENAME'           => $fileName,

                        'FILTER_CODE'        => $row[30] ?? null,
                        'IMEI'               => $row[31] ?? null,
                        'LAST_PARTIAL'       => $row[32] ?? null,
                        'NE'                 => $row[33] ?? null,
                        'ORIG_START_TIME'    => $row[34] ?? null,
                        'PARTIAL_SEQ_ID'     => $row[35] ?? null,
                        'PARTNER'            => $row[36] ?? null,
                        'PARTNER_CODE'       => $row[37] ?? null,
                        'PGW_ADDRESS'        => $row[38] ?? null,
                        'PRICE_PLAN_CODE'    => $row[39] ?? null,
                        'PROC_DATE'          => $row[40] ?? null,
                        'PROC_HOUR'          => $row[41] ?? null,
                        'RADIO_TYPE'         => $row[42] ?? null,
                        'RATE_CODE'          => $row[43] ?? null,
                        'RECORD_ID'          => $row[44] ?? null,
                        'RECORD_STATUS'      => $row[45] ?? null,
                        'RECORD_TYPE'        => $row[46] ?? null,
                        'ROAMING_TYPE'       => $row[47] ?? null,
                        'SERVED_MSRN'        => $row[48] ?? null,
                        'SERVICE_ID'         => $row[49] ?? null,
                        'SERVICE_PARTNER'    => $row[50] ?? null,
                        'SERVICE_TYPE'       => $row[51] ?? null,
                        'SGSN_ADDRESS'       => $row[52] ?? null,
                        'SMS_CENTRE'         => $row[53] ?? null,
                        'START_DATE_TIME_HOME'=> $row[54] ?? null,
                        'START_TIME'         => $row[55] ?? null,
                        'SUBSCRIBER_TYPE'    => $row[56] ?? null,
                        'TELESERVICE'        => $row[57] ?? null,
                        'TEST_FLAG'          => $row[58] ?? null,
                        'TON_A'              => $row[59] ?? null,
                        'TON_B'              => $row[60] ?? null,
                        'TON_C'              => $row[61] ?? null,
                        'TRAFFIC_TYPE'       => $row[62] ?? null,
                        'TRUNK_IN'           => $row[63] ?? null,
                        'TRUNK_OUT'          => $row[64] ?? null,
                    ];

                    if (count($batch) >= $batchSize) {
                        DB::table('ra_t_tmp_occ')->insert($batch);
                        $insertedTmp += count($batch);
                        $batch = [];
                    }
                }

                if (!empty($batch)) {
                    DB::table('ra_t_tmp_occ')->insert($batch);
                    $insertedTmp += count($batch);
                }

                fclose($handle);
                $handle = null;

                $this->info("TMP rows: {$insertedTmp}");

                /**
                 * 2) DETAIL
                 */
                DB::statement("
                    INSERT INTO ra_t_detail_occ (
                        A_MSISDN,
                        B_MSISDN,
                        CALL_TYPE,
                        CHARGE_AMOUNT_ORIG,
                        EVENT_STATUS,
                        EVENT_TYPE,
                        FILENAME,
                        ORIG_START_TIME,
                        PARTNER,
                        SUBSCRIBER_TYPE,
                        TRAFFIC_TYPE
                    )
                    SELECT
                        TRIM(A_MSISDN),
                        TRIM(B_MSISDN),
                        TRIM(CALL_TYPE),
                        TRIM(CHARGE_AMOUNT_ORIG),
                        TRIM(EVENT_STATUS),
                        TRIM(EVENT_TYPE),
                        FILENAME,
                        TRIM(ORIG_START_TIME),
                        TRIM(PARTNER),
                        TRIM(SUBSCRIBER_TYPE),
                        TRIM(TRAFFIC_TYPE)
                    FROM ra_t_tmp_occ
                    WHERE FILENAME = ?
                ", [$fileName]);

                DB::commit();

                /**
                 * 3) MOVE
                 */
                File::move($filePath, $processedPath . DIRECTORY_SEPARATOR . $fileName);

                $this->info("SUCCESS: {$fileName}");

            } catch (Throwable $e) {

                DB::rollBack();

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
}
