<?php

namespace App\Http\Controllers\Operations;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Throwable;

class AggregationController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $this->filters($request);
        $errors = [];

        return view('operations.aggregation.index', [
            'activeRole' => User::ROLE_OPERATIONAL,
            'roleLabel' => User::roleLabel(User::ROLE_OPERATIONAL),
            'filters' => $filters,
            'summary' => $this->safe(fn () => $this->summary($filters), $this->emptySummary(), $errors, 'Resume AGG'),
            'tableStats' => $this->safe(fn () => $this->tableStats($filters), [], $errors, 'Statistiques tables'),
            'dailyRows' => $this->safe(fn () => $this->dailyRows($filters), [], $errors, 'Aggregation journaliere'),
            'hourlyRows' => $this->safe(fn () => $this->hourlyRows($filters), [], $errors, 'Aggregation horaire'),
            'topRows' => $this->safe(fn () => $this->topRows($filters), [], $errors, 'Top agregations'),
            'recentRows' => $this->safe(fn () => $this->recentRows($filters), [], $errors, 'Dernieres agregations'),
            'errors' => $errors,
        ]);
    }

    private function filters(Request $request): array
    {
        $source = strtolower((string) $request->query('source', 'all'));

        if (! in_array($source, ['all', 'mmg', 'occ'], true)) {
            $source = 'all';
        }

        return [
            'source' => $source,
            'date_from' => $this->dateValue($request->query('date_from')),
            'date_to' => $this->dateValue($request->query('date_to')),
        ];
    }

    private function summary(array $filters): array
    {
        $mmgCdr = $this->includes($filters, 'mmg') ? $this->sumCdr('ra_t_agg_mmg', $filters) : 0;
        $occCdr = $this->includes($filters, 'occ') ? $this->sumCdr('ra_t_agg_occ', $filters) : 0;
        $mmgRows = $this->includes($filters, 'mmg') ? $this->countRows('ra_t_agg_mmg', $filters) : 0;
        $occRows = $this->includes($filters, 'occ') ? $this->countRows('ra_t_agg_occ', $filters) : 0;
        $occAmount = $this->includes($filters, 'occ') ? $this->sumAmount('ra_t_agg_occ', $filters) : 0;
        $latestDates = [];

        if ($this->includes($filters, 'mmg')) {
            $latestDates[] = $this->maxCreatedAt('ra_t_agg_mmg', $filters);
        }

        if ($this->includes($filters, 'occ')) {
            $latestDates[] = $this->maxCreatedAt('ra_t_agg_occ', $filters);
        }

        return [
            'total_cdr' => $mmgCdr + $occCdr,
            'mmg_cdr' => $mmgCdr,
            'occ_cdr' => $occCdr,
            'occ_amount' => $occAmount,
            'agg_rows' => $mmgRows + $occRows,
            'latest_agg' => $this->latestDate($latestDates),
        ];
    }

    private function tableStats(array $filters): array
    {
        $tables = [];

        if ($this->includes($filters, 'mmg')) {
            $tables[] = $this->aggTableStats('MMG', 'ra_t_agg_mmg', $filters, false);
        }

        if ($this->includes($filters, 'occ')) {
            $tables[] = $this->aggTableStats('OCC', 'ra_t_agg_occ', $filters, true);
        }

        return $tables;
    }

    private function dailyRows(array $filters): array
    {
        $rows = [];

        if ($this->includes($filters, 'mmg')) {
            foreach ($this->dailySourceRows('MMG', 'ra_t_agg_mmg', $filters, false) as $row) {
                $key = $row['sort_key'];
                $rows[$key] ??= ['date' => $row['date'], 'sort_key' => $key, 'mmg_cdr' => 0, 'occ_cdr' => 0, 'amount' => 0];
                $rows[$key]['mmg_cdr'] += $row['cdr_count'];
            }
        }

        if ($this->includes($filters, 'occ')) {
            foreach ($this->dailySourceRows('OCC', 'ra_t_agg_occ', $filters, true) as $row) {
                $key = $row['sort_key'];
                $rows[$key] ??= ['date' => $row['date'], 'sort_key' => $key, 'mmg_cdr' => 0, 'occ_cdr' => 0, 'amount' => 0];
                $rows[$key]['occ_cdr'] += $row['cdr_count'];
                $rows[$key]['amount'] += $row['amount'];
            }
        }

        usort($rows, fn (array $a, array $b): int => strcmp($b['sort_key'], $a['sort_key']));

        return array_slice(array_values($rows), 0, 14);
    }

    private function hourlyRows(array $filters): array
    {
        $rows = [];

        if ($this->includes($filters, 'mmg')) {
            foreach ($this->hourlySourceRows('MMG', 'ra_t_agg_mmg', $filters, false) as $row) {
                $key = (int) $row['hour'];
                $rows[$key] ??= ['hour' => $key, 'mmg_cdr' => 0, 'occ_cdr' => 0, 'amount' => 0];
                $rows[$key]['mmg_cdr'] += $row['cdr_count'];
            }
        }

        if ($this->includes($filters, 'occ')) {
            foreach ($this->hourlySourceRows('OCC', 'ra_t_agg_occ', $filters, true) as $row) {
                $key = (int) $row['hour'];
                $rows[$key] ??= ['hour' => $key, 'mmg_cdr' => 0, 'occ_cdr' => 0, 'amount' => 0];
                $rows[$key]['occ_cdr'] += $row['cdr_count'];
                $rows[$key]['amount'] += $row['amount'];
            }
        }

        ksort($rows);

        return array_values($rows);
    }

    private function topRows(array $filters): array
    {
        $rows = [];

        if ($this->includes($filters, 'mmg')) {
            $query = DB::table('ra_t_agg_mmg')
                ->select(
                    DB::raw("'MMG' AS SOURCE_NAME"),
                    DB::raw("COALESCE(SERVICE_TYPE, 'N/A') AS DIMENSION_NAME"),
                    DB::raw('SUM(CDR_COUNT) AS CDR_COUNT'),
                    DB::raw('0 AS CHARGE_AMOUNT')
                )
                ->groupBy(DB::raw("COALESCE(SERVICE_TYPE, 'N/A')"))
                ->orderBy(DB::raw("COALESCE(SERVICE_TYPE, 'N/A')"));

            $this->applyDateFilter($query, $filters);
            $rows = array_merge($rows, $this->mapTopRows($query->get()));
        }

        if ($this->includes($filters, 'occ')) {
            $query = DB::table('ra_t_agg_occ')
                ->select(
                    DB::raw("'OCC' AS SOURCE_NAME"),
                    DB::raw("COALESCE(KEYWORD, 'N/A') AS DIMENSION_NAME"),
                    DB::raw('SUM(CDR_COUNT) AS CDR_COUNT'),
                    DB::raw('SUM(CHARGE_AMOUNT) AS CHARGE_AMOUNT')
                )
                ->groupBy(DB::raw("COALESCE(KEYWORD, 'N/A')"))
                ->orderBy(DB::raw("COALESCE(KEYWORD, 'N/A')"));

            $this->applyDateFilter($query, $filters);
            $rows = array_merge($rows, $this->mapTopRows($query->get()));
        }

        usort($rows, fn (array $a, array $b): int => $b['cdr_count'] <=> $a['cdr_count']);

        return array_slice($rows, 0, 10);
    }

    private function recentRows(array $filters): array
    {
        $rows = [];

        if ($this->includes($filters, 'mmg')) {
            $query = DB::table('ra_t_agg_mmg')
                ->select(
                    DB::raw("'MMG' AS SOURCE_NAME"),
                    'START_DATE',
                    'START_HOUR',
                    'EVENT_TYPE',
                    'CALL_TYPE',
                    DB::raw('SERVICE_TYPE AS DIMENSION_NAME'),
                    'CDR_COUNT',
                    DB::raw('0 AS CHARGE_AMOUNT'),
                    'CREATED_AT'
                )
                ->orderByDesc('CREATED_AT')
                ->limit(12);

            $this->applyDateFilter($query, $filters);
            $rows = array_merge($rows, $this->mapRecentRows($query->get()));
        }

        if ($this->includes($filters, 'occ')) {
            $query = DB::table('ra_t_agg_occ')
                ->select(
                    DB::raw("'OCC' AS SOURCE_NAME"),
                    'START_DATE',
                    'START_HOUR',
                    'EVENT_TYPE',
                    'CALL_TYPE',
                    DB::raw('KEYWORD AS DIMENSION_NAME'),
                    'CDR_COUNT',
                    'CHARGE_AMOUNT',
                    'CREATED_AT'
                )
                ->orderByDesc('CREATED_AT')
                ->limit(12);

            $this->applyDateFilter($query, $filters);
            $rows = array_merge($rows, $this->mapRecentRows($query->get()));
        }

        usort($rows, fn (array $a, array $b): int => $b['created_ts'] <=> $a['created_ts']);

        return array_slice($rows, 0, 12);
    }

    private function dailySourceRows(string $source, string $table, array $filters, bool $withAmount): array
    {
        $query = DB::table($table)
            ->select(
                'START_DATE',
                DB::raw('SUM(CDR_COUNT) AS CDR_COUNT'),
                DB::raw($withAmount ? 'SUM(CHARGE_AMOUNT) AS CHARGE_AMOUNT' : '0 AS CHARGE_AMOUNT')
            )
            ->whereNotNull('START_DATE')
            ->groupBy('START_DATE')
            ->orderByDesc('START_DATE')
            ->limit(30);

        $this->applyDateFilter($query, $filters);

        return $query->get()
            ->map(fn (object $row): array => [
                'source' => $source,
                'date' => $this->formatDate($this->objectValue($row, 'START_DATE'), 'd/m/Y'),
                'sort_key' => $this->dateSortKey($this->objectValue($row, 'START_DATE')),
                'cdr_count' => (int) $this->objectValue($row, 'CDR_COUNT'),
                'amount' => (float) ($this->objectValue($row, 'CHARGE_AMOUNT') ?? 0),
            ])
            ->all();
    }

    private function hourlySourceRows(string $source, string $table, array $filters, bool $withAmount): array
    {
        $query = DB::table($table)
            ->select(
                'START_HOUR',
                DB::raw('SUM(CDR_COUNT) AS CDR_COUNT'),
                DB::raw($withAmount ? 'SUM(CHARGE_AMOUNT) AS CHARGE_AMOUNT' : '0 AS CHARGE_AMOUNT')
            )
            ->whereNotNull('START_HOUR')
            ->groupBy('START_HOUR')
            ->orderBy('START_HOUR');

        $this->applyDateFilter($query, $filters);

        return $query->get()
            ->map(fn (object $row): array => [
                'source' => $source,
                'hour' => (int) $this->objectValue($row, 'START_HOUR'),
                'cdr_count' => (int) $this->objectValue($row, 'CDR_COUNT'),
                'amount' => (float) ($this->objectValue($row, 'CHARGE_AMOUNT') ?? 0),
            ])
            ->all();
    }

    private function mapTopRows($rows): array
    {
        return collect($rows)
            ->map(fn (object $row): array => [
                'source' => (string) $this->objectValue($row, 'SOURCE_NAME'),
                'dimension' => (string) ($this->objectValue($row, 'DIMENSION_NAME') ?: 'N/A'),
                'cdr_count' => (int) $this->objectValue($row, 'CDR_COUNT'),
                'amount' => (float) ($this->objectValue($row, 'CHARGE_AMOUNT') ?? 0),
            ])
            ->all();
    }

    private function mapRecentRows($rows): array
    {
        return collect($rows)
            ->map(fn (object $row): array => [
                'source' => (string) $this->objectValue($row, 'SOURCE_NAME'),
                'start_date' => $this->formatDate($this->objectValue($row, 'START_DATE'), 'd/m/Y'),
                'start_hour' => $this->objectValue($row, 'START_HOUR') !== null
                    ? str_pad((string) $this->objectValue($row, 'START_HOUR'), 2, '0', STR_PAD_LEFT)
                    : '--',
                'event_type' => (string) ($this->objectValue($row, 'EVENT_TYPE') ?: '--'),
                'call_type' => (string) ($this->objectValue($row, 'CALL_TYPE') ?: '--'),
                'dimension' => (string) ($this->objectValue($row, 'DIMENSION_NAME') ?: 'N/A'),
                'cdr_count' => (int) $this->objectValue($row, 'CDR_COUNT'),
                'amount' => (float) ($this->objectValue($row, 'CHARGE_AMOUNT') ?? 0),
                'created_at' => $this->formatDate($this->objectValue($row, 'CREATED_AT')),
                'created_ts' => $this->timestamp($this->objectValue($row, 'CREATED_AT')),
            ])
            ->all();
    }

    private function aggTableStats(string $source, string $table, array $filters, bool $withAmount): array
    {
        $query = DB::table($table);
        $this->applyDateFilter($query, $filters);

        return [
            'source' => $source,
            'table' => strtoupper($table),
            'rows' => (int) (clone $query)->count(),
            'cdr_count' => (int) (clone $query)->sum('CDR_COUNT'),
            'amount' => $withAmount ? (float) (clone $query)->sum('CHARGE_AMOUNT') : 0,
            'latest' => $this->formatDate((clone $query)->max('CREATED_AT')),
        ];
    }

    private function countRows(string $table, array $filters): int
    {
        $query = DB::table($table);
        $this->applyDateFilter($query, $filters);

        return (int) $query->count();
    }

    private function sumCdr(string $table, array $filters): int
    {
        $query = DB::table($table);
        $this->applyDateFilter($query, $filters);

        return (int) $query->sum('CDR_COUNT');
    }

    private function sumAmount(string $table, array $filters): float
    {
        $query = DB::table($table);
        $this->applyDateFilter($query, $filters);

        return (float) $query->sum('CHARGE_AMOUNT');
    }

    private function maxCreatedAt(string $table, array $filters): mixed
    {
        $query = DB::table($table);
        $this->applyDateFilter($query, $filters);

        return $query->max('CREATED_AT');
    }

    private function applyDateFilter($query, array $filters): void
    {
        if ($filters['date_from']) {
            $query->whereRaw("START_DATE >= TO_DATE(?, 'YYYY-MM-DD')", [$filters['date_from']]);
        }

        if ($filters['date_to']) {
            $query->whereRaw("START_DATE < TO_DATE(?, 'YYYY-MM-DD') + 1", [$filters['date_to']]);
        }
    }

    private function safe(callable $callback, mixed $fallback, array &$errors, string $label): mixed
    {
        try {
            return $callback();
        } catch (Throwable $e) {
            $errors[] = "{$label}: {$e->getMessage()}";

            return $fallback;
        }
    }

    private function includes(array $filters, string $source): bool
    {
        return $filters['source'] === 'all' || $filters['source'] === $source;
    }

    private function emptySummary(): array
    {
        return [
            'total_cdr' => 0,
            'mmg_cdr' => 0,
            'occ_cdr' => 0,
            'occ_amount' => 0,
            'agg_rows' => 0,
            'latest_agg' => '--',
        ];
    }

    private function latestDate(array $dates): string
    {
        $latest = null;

        foreach ($dates as $date) {
            $timestamp = $this->timestamp($date);

            if ($timestamp > ($latest['timestamp'] ?? 0)) {
                $latest = ['timestamp' => $timestamp, 'value' => $date];
            }
        }

        return $latest ? $this->formatDate($latest['value']) : '--';
    }

    private function dateValue(mixed $value): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return Carbon::parse($value)->format('Y-m-d');
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

    private function formatDate(mixed $value, string $format = 'd/m/Y H:i:s'): string
    {
        if ($value === null || $value === '') {
            return '--';
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format($format);
        }

        try {
            return Carbon::parse((string) $value)->format($format);
        } catch (Throwable) {
            return (string) $value;
        }
    }

    private function dateSortKey(mixed $value): string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        try {
            return Carbon::parse((string) $value)->format('Y-m-d');
        } catch (Throwable) {
            return (string) $value;
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
}
