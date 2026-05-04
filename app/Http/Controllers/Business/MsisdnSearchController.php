<?php

namespace App\Http\Controllers\Business;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use SimpleXMLElement;
use ZipArchive;

class MsisdnSearchController extends Controller
{
    public function search(Request $request): View
    {
        $searchInput = trim((string) $request->query('msisdn', ''));
        $msisdn = $searchInput === '' ? null : $this->normalizeMsisdn($searchInput);

        $searchAttempted = $searchInput !== '';
        $searchError = null;
        $stats = $this->emptyStats();
        $mmgSummary = [];
        $occSummary = [];
        $recentActivities = [];

        if ($searchAttempted && $msisdn === null) {
            $searchError = 'MSISDN invalide. Utilisez uniquement des chiffres.';
        }

        if ($msisdn !== null) {
            $stats = $this->buildSingleStats($msisdn);
            $mmgSummary = $this->searchMmgSummary($msisdn);
            $occSummary = $this->searchOccSummary($msisdn);
            $recentActivities = $this->searchRecentActivities($msisdn);
        }

        return view('business.msisdn.search', [
            'activeRole' => User::ROLE_BUSINESS,
            'roleLabel' => User::roleLabel(User::ROLE_BUSINESS),
            'searchInput' => $searchInput,
            'normalizedMsisdn' => $msisdn,
            'searchAttempted' => $searchAttempted,
            'searchError' => $searchError,
            'stats' => $stats,
            'mmgSummary' => $mmgSummary,
            'occSummary' => $occSummary,
            'recentActivities' => $recentActivities,
            'results' => [],
            'summary' => $this->emptyExcelSummary(),
            'uploadedCount' => 0,
            'fileName' => null,
        ]);
    }

    public function excel(): View
    {
        return view('business.msisdn.search', [
            'activeRole' => User::ROLE_BUSINESS,
            'roleLabel' => User::roleLabel(User::ROLE_BUSINESS),
            'searchInput' => '',
            'normalizedMsisdn' => null,
            'searchAttempted' => false,
            'searchError' => null,
            'stats' => $this->emptyStats(),
            'mmgSummary' => [],
            'occSummary' => [],
            'recentActivities' => [],
            'results' => [],
            'summary' => $this->emptyExcelSummary(),
            'uploadedCount' => 0,
            'fileName' => null,
        ]);
    }

    public function excelSearch(Request $request): View
    {
        $data = $request->validate([
            'file' => ['required', 'file', 'max:4096', 'mimes:xlsx,csv,txt'],
        ]);

        /** @var UploadedFile $file */
        $file = $data['file'];
        $msisdns = $this->extractMsisdnsFromFile($file);

        if ($msisdns === []) {
            throw ValidationException::withMessages([
                'file' => 'Aucun MSISDN exploitable trouve dans le fichier.',
            ]);
        }

        if (count($msisdns) > 500) {
            throw ValidationException::withMessages([
                'file' => 'La liste est limitee a 500 MSISDN par import.',
            ]);
        }

        $results = $this->buildExcelResults($msisdns);

        return view('business.msisdn.search', [
            'activeRole' => User::ROLE_BUSINESS,
            'roleLabel' => User::roleLabel(User::ROLE_BUSINESS),
            'searchInput' => '',
            'normalizedMsisdn' => null,
            'searchAttempted' => false,
            'searchError' => null,
            'stats' => $this->emptyStats(),
            'mmgSummary' => [],
            'occSummary' => [],
            'recentActivities' => [],
            'results' => $results,
            'summary' => $this->buildExcelSummary($results),
            'uploadedCount' => count($msisdns),
            'fileName' => $file->getClientOriginalName(),
        ]);
    }

    /**
     * @return array<string, int|float|string|null>
     */
    private function emptyStats(): array
    {
        return [
            'mmg_count' => 0,
            'occ_count' => 0,
            'occ_amount' => 0.0,
            'sources' => 0,
            'last_activity' => null,
        ];
    }

    /**
     * @return array<string, int|float>
     */
    private function emptyExcelSummary(): array
    {
        return [
            'matched_msisdns' => 0,
            'mmg_hits' => 0,
            'occ_hits' => 0,
            'occ_amount' => 0.0,
        ];
    }

    /**
     * @return array<string, int|float|string|null>
     */
    private function buildSingleStats(string $msisdn): array
    {
        $mmg = DB::table('ra_t_tmp_mmg')
            ->selectRaw('COUNT(*) as total, MAX(TRIM(ORIG_START_TIME)) as last_activity')
            ->where($this->msisdnFilter('A_MSISDN', 'A_MSISDN_ORIG', $msisdn))
            ->first();

        $occ = DB::table('ra_t_tmp_occ')
            ->selectRaw("
                COUNT(*) as total,
                MAX(TRIM(ORIG_START_TIME)) as last_activity,
                SUM(
                    CASE
                        WHEN REGEXP_LIKE(TRIM(CHARGE_AMOUNT_ORIG), '^-?[0-9]+(\.[0-9]+)?$')
                            THEN TO_NUMBER(TRIM(CHARGE_AMOUNT_ORIG))
                        ELSE 0
                    END
                ) as total_amount
            ")
            ->where($this->msisdnFilter('A_MSISDN', 'A_MSISDN_ORIG', $msisdn))
            ->first();

        $mmgCount = (int) ($mmg->total ?? 0);
        $occCount = (int) ($occ->total ?? 0);
        $occAmount = (float) ($occ->total_amount ?? 0);

        return [
            'mmg_count' => $mmgCount,
            'occ_count' => $occCount,
            'occ_amount' => $occAmount,
            'sources' => ($mmgCount > 0 ? 1 : 0) + ($occCount > 0 ? 1 : 0),
            'last_activity' => $this->maxActivityTime(
                $mmg->last_activity ?? null,
                $occ->last_activity ?? null
            ),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function searchMmgSummary(string $msisdn): array
    {
        return DB::table('ra_t_tmp_mmg')
            ->selectRaw("
                TRIM(SERVICE_TYPE) as service_type,
                TRIM(EVENT_TYPE_ORIG) as event_type_orig,
                TRIM(B_MSISDN) as b_msisdn,
                TRIM(SUBSCRIBER_TYPE) as subscriber_type,
                COUNT(*) as cdr_count,
                MIN(TRIM(ORIG_START_TIME)) as first_activity,
                MAX(TRIM(ORIG_START_TIME)) as last_activity
            ")
            ->where($this->msisdnFilter('A_MSISDN', 'A_MSISDN_ORIG', $msisdn))
            ->groupByRaw('TRIM(SERVICE_TYPE), TRIM(EVENT_TYPE_ORIG), TRIM(B_MSISDN), TRIM(SUBSCRIBER_TYPE)')
            ->orderByRaw('COUNT(*) DESC, MAX(TRIM(ORIG_START_TIME)) DESC')
            ->get()
            ->map(fn ($row) => (array) $row)
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function searchOccSummary(string $msisdn): array
    {
        return DB::table('ra_t_tmp_occ as t')
            ->leftJoin('services as s', function ($join): void {
                $join->whereRaw('TRIM(t.SERVICE_ID) = TO_CHAR(s.ID)');
            })
            ->selectRaw("
                COALESCE(s.service_name, TRIM(t.PARTNER), TRIM(t.B_MSISDN)) as service_name,
                TRIM(s.short_code) as short_code,
                TRIM(s.keyword) as keyword,
                TRIM(t.PARTNER) as partner,
                TRIM(t.SUBSCRIBER_TYPE) as subscriber_type,
                COUNT(*) as cdr_count,
                SUM(
                    CASE
                        WHEN REGEXP_LIKE(TRIM(t.CHARGE_AMOUNT_ORIG), '^-?[0-9]+(\.[0-9]+)?$')
                            THEN TO_NUMBER(TRIM(t.CHARGE_AMOUNT_ORIG))
                        ELSE 0
                    END
                ) as total_amount,
                MIN(TRIM(t.ORIG_START_TIME)) as first_activity,
                MAX(TRIM(t.ORIG_START_TIME)) as last_activity
            ")
            ->where($this->msisdnFilter('t.A_MSISDN', 't.A_MSISDN_ORIG', $msisdn))
            ->groupByRaw("
                COALESCE(s.service_name, TRIM(t.PARTNER), TRIM(t.B_MSISDN)),
                TRIM(s.short_code),
                TRIM(s.keyword),
                TRIM(t.PARTNER),
                TRIM(t.SUBSCRIBER_TYPE)
            ")
            ->orderByRaw("
                SUM(
                    CASE
                        WHEN REGEXP_LIKE(TRIM(t.CHARGE_AMOUNT_ORIG), '^-?[0-9]+(\.[0-9]+)?$')
                            THEN TO_NUMBER(TRIM(t.CHARGE_AMOUNT_ORIG))
                        ELSE 0
                    END
                ) DESC,
                COUNT(*) DESC
            ")
            ->get()
            ->map(fn ($row) => (array) $row)
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function searchRecentActivities(string $msisdn): array
    {
        $mmgRows = DB::table('ra_t_tmp_mmg')
            ->selectRaw("
                'MMG' as source,
                TRIM(ORIG_START_TIME) as activity_time,
                TRIM(A_MSISDN) as a_msisdn,
                TRIM(B_MSISDN) as b_msisdn,
                TRIM(EVENT_TYPE_ORIG) as activity_label,
                TRIM(SERVICE_TYPE) as service_hint,
                NULL as amount
            ")
            ->where($this->msisdnFilter('A_MSISDN', 'A_MSISDN_ORIG', $msisdn))
            ->orderByRaw('TRIM(ORIG_START_TIME) DESC')
            ->limit(10)
            ->get()
            ->map(fn ($row) => (array) $row);

        $occRows = DB::table('ra_t_tmp_occ as t')
            ->leftJoin('services as s', function ($join): void {
                $join->whereRaw('TRIM(t.SERVICE_ID) = TO_CHAR(s.ID)');
            })
            ->selectRaw("
                'OCC' as source,
                TRIM(t.ORIG_START_TIME) as activity_time,
                TRIM(t.A_MSISDN) as a_msisdn,
                TRIM(t.B_MSISDN) as b_msisdn,
                COALESCE(s.service_name, TRIM(t.PARTNER), TRIM(t.B_MSISDN)) as activity_label,
                TRIM(t.PARTNER) as service_hint,
                CASE
                    WHEN REGEXP_LIKE(TRIM(t.CHARGE_AMOUNT_ORIG), '^-?[0-9]+(\.[0-9]+)?$')
                        THEN TO_NUMBER(TRIM(t.CHARGE_AMOUNT_ORIG))
                    ELSE 0
                END as amount
            ")
            ->where($this->msisdnFilter('t.A_MSISDN', 't.A_MSISDN_ORIG', $msisdn))
            ->orderByRaw('TRIM(t.ORIG_START_TIME) DESC')
            ->limit(10)
            ->get()
            ->map(fn ($row) => (array) $row);

        return $mmgRows
            ->concat($occRows)
            ->sortByDesc('activity_time')
            ->take(12)
            ->values()
            ->all();
    }

    /**
     * @param array<int, string> $msisdns
     * @return array<int, array<string, mixed>>
     */
    private function buildExcelResults(array $msisdns): array
    {
        $mmgRows = DB::table('ra_t_tmp_mmg')
            ->selectRaw('TRIM(A_MSISDN) as msisdn, COUNT(*) as mmg_count, MAX(TRIM(ORIG_START_TIME)) as last_mmg')
            ->whereIn(DB::raw('TRIM(A_MSISDN)'), $msisdns)
            ->groupByRaw('TRIM(A_MSISDN)')
            ->get()
            ->keyBy('msisdn');

        $occRows = DB::table('ra_t_tmp_occ')
            ->selectRaw("
                TRIM(A_MSISDN) as msisdn,
                COUNT(*) as occ_count,
                MAX(TRIM(ORIG_START_TIME)) as last_occ,
                SUM(
                    CASE
                        WHEN REGEXP_LIKE(TRIM(CHARGE_AMOUNT_ORIG), '^-?[0-9]+(\.[0-9]+)?$')
                            THEN TO_NUMBER(TRIM(CHARGE_AMOUNT_ORIG))
                        ELSE 0
                    END
                ) as occ_amount
            ")
            ->whereIn(DB::raw('TRIM(A_MSISDN)'), $msisdns)
            ->groupByRaw('TRIM(A_MSISDN)')
            ->get()
            ->keyBy('msisdn');

        $results = [];

        foreach ($msisdns as $msisdn) {
            $mmg = $mmgRows->get($msisdn);
            $occ = $occRows->get($msisdn);

            $results[] = [
                'msisdn' => $msisdn,
                'mmg_count' => (int) ($mmg->mmg_count ?? 0),
                'occ_count' => (int) ($occ->occ_count ?? 0),
                'occ_amount' => (float) ($occ->occ_amount ?? 0),
                'last_activity' => $this->maxActivityTime(
                    $mmg->last_mmg ?? null,
                    $occ->last_occ ?? null
                ),
            ];
        }

        usort($results, function (array $left, array $right): int {
            return [$right['mmg_count'] + $right['occ_count'], $right['occ_amount']]
                <=> [$left['mmg_count'] + $left['occ_count'], $left['occ_amount']];
        });

        return $results;
    }

    /**
     * @param array<int, array<string, mixed>> $results
     * @return array<string, int|float>
     */
    private function buildExcelSummary(array $results): array
    {
        $summary = $this->emptyExcelSummary();

        foreach ($results as $row) {
            $totalHits = ((int) $row['mmg_count']) + ((int) $row['occ_count']);

            if ($totalHits > 0) {
                $summary['matched_msisdns']++;
            }

            $summary['mmg_hits'] += (int) $row['mmg_count'];
            $summary['occ_hits'] += (int) $row['occ_count'];
            $summary['occ_amount'] += (float) $row['occ_amount'];
        }

        return $summary;
    }

    private function msisdnFilter(string $primaryColumn, string $secondaryColumn, string $msisdn): \Closure
    {
        return function (Builder $query) use ($primaryColumn, $secondaryColumn, $msisdn): void {
            $query
                ->whereRaw("TRIM({$primaryColumn}) = ?", [$msisdn])
                ->orWhereRaw("TRIM({$secondaryColumn}) = ?", [$msisdn]);
        };
    }

    private function maxActivityTime(?string $left, ?string $right): ?string
    {
        if ($left === null || $left === '') {
            return $right;
        }

        if ($right === null || $right === '') {
            return $left;
        }

        return strcmp($left, $right) >= 0 ? $left : $right;
    }

    /**
     * @return array<int, string>
     */
    private function extractMsisdnsFromFile(UploadedFile $file): array
    {
        $extension = strtolower((string) $file->getClientOriginalExtension());

        $values = match ($extension) {
            'xlsx' => $this->readXlsxValues($file->getRealPath() ?: $file->getPathname()),
            'csv', 'txt' => $this->readDelimitedValues($file->getRealPath() ?: $file->getPathname()),
            default => [],
        };

        $msisdns = [];

        foreach ($values as $value) {
            $msisdn = $this->normalizeMsisdn($value);

            if ($msisdn !== null) {
                $msisdns[$msisdn] = $msisdn;
            }
        }

        return array_values($msisdns);
    }

    /**
     * @return array<int, string>
     */
    private function readDelimitedValues(string $path): array
    {
        $handle = fopen($path, 'r');

        if ($handle === false) {
            return [];
        }

        $firstLine = fgets($handle);
        rewind($handle);

        $delimiter = ',';
        $scores = [
            ',' => substr_count((string) $firstLine, ','),
            ';' => substr_count((string) $firstLine, ';'),
            "\t" => substr_count((string) $firstLine, "\t"),
        ];

        arsort($scores);
        $candidate = array_key_first($scores);

        if (is_string($candidate) && $scores[$candidate] > 0) {
            $delimiter = $candidate;
        }

        $values = [];

        while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
            foreach ($row as $cell) {
                $values[] = (string) $cell;
            }
        }

        fclose($handle);

        return $values;
    }

    /**
     * @return array<int, string>
     */
    private function readXlsxValues(string $path): array
    {
        $zip = new ZipArchive();

        if ($zip->open($path) !== true) {
            return [];
        }

        $sharedStrings = $this->readSharedStrings($zip);
        $worksheetPath = $this->resolveFirstWorksheetPath($zip);

        if ($worksheetPath === null) {
            $zip->close();
            return [];
        }

        $sheetXml = $zip->getFromName($worksheetPath);
        $zip->close();

        if ($sheetXml === false) {
            return [];
        }

        $sheet = simplexml_load_string($sheetXml);

        if (!$sheet instanceof SimpleXMLElement || !isset($sheet->sheetData)) {
            return [];
        }

        $values = [];

        foreach ($sheet->sheetData->row as $row) {
            foreach ($row->c as $cell) {
                $values[] = $this->xlsxCellValue($cell, $sharedStrings);
            }
        }

        return $values;
    }

    /**
     * @return array<int, string>
     */
    private function readSharedStrings(ZipArchive $zip): array
    {
        $xml = $zip->getFromName('xl/sharedStrings.xml');

        if ($xml === false) {
            return [];
        }

        $document = simplexml_load_string($xml);

        if (!$document instanceof SimpleXMLElement) {
            return [];
        }

        $strings = [];

        foreach ($document->si as $item) {
            if (isset($item->t)) {
                $strings[] = (string) $item->t;
                continue;
            }

            $text = '';

            foreach ($item->r as $run) {
                $text .= (string) $run->t;
            }

            $strings[] = $text;
        }

        return $strings;
    }

    private function resolveFirstWorksheetPath(ZipArchive $zip): ?string
    {
        $workbookXml = $zip->getFromName('xl/workbook.xml');
        $relsXml = $zip->getFromName('xl/_rels/workbook.xml.rels');

        if ($workbookXml === false || $relsXml === false) {
            return null;
        }

        $workbook = simplexml_load_string($workbookXml);
        $relationships = simplexml_load_string($relsXml);

        if (!$workbook instanceof SimpleXMLElement || !$relationships instanceof SimpleXMLElement) {
            return null;
        }

        $namespaces = $workbook->getNamespaces(true);
        $relationshipNamespace = $namespaces['r'] ?? 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';
        $sheets = $workbook->sheets;

        if (!isset($sheets->sheet[0])) {
            return null;
        }

        $attributes = $sheets->sheet[0]->attributes($relationshipNamespace, true);
        $relationshipId = (string) ($attributes['id'] ?? '');

        if ($relationshipId === '') {
            return null;
        }

        foreach ($relationships->Relationship as $relationship) {
            $relationshipAttributes = $relationship->attributes();

            if ((string) ($relationshipAttributes['Id'] ?? '') !== $relationshipId) {
                continue;
            }

            $target = (string) ($relationshipAttributes['Target'] ?? '');

            if ($target === '') {
                return null;
            }

            return str_starts_with($target, 'xl/')
                ? $target
                : 'xl/' . ltrim($target, '/');
        }

        return null;
    }

    /**
     * @param array<int, string> $sharedStrings
     */
    private function xlsxCellValue(SimpleXMLElement $cell, array $sharedStrings): string
    {
        $attributes = $cell->attributes();
        $type = (string) ($attributes['t'] ?? '');

        if ($type === 'inlineStr' && isset($cell->is->t)) {
            return (string) $cell->is->t;
        }

        $value = (string) ($cell->v ?? '');

        if ($type === 's') {
            return $sharedStrings[(int) $value] ?? '';
        }

        return $value;
    }

    private function normalizeMsisdn(string $value): ?string
    {
        $value = trim($value);

        if ($value === '') {
            return null;
        }

        $value = preg_replace('/\.0+$/', '', $value);
        $value = preg_replace('/\D+/', '', (string) $value);

        if ($value === null || strlen($value) < 8 || strlen($value) > 20) {
            return null;
        }

        return $value;
    }
}
