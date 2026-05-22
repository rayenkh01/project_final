<?php

namespace App\Http\Controllers;

use App\Models\Service;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Throwable;

class DashboardController extends Controller
{
    public function index(Request $request): View
    {
        $activeRole = $this->activeRole($request);
        $revenueEvolution = $this->revenueEvolution();
        $topServices = $this->topServices();

        return view('dashboard.index', [
            'activeRole' => $activeRole,
            'roleLabel' => $this->roleLabel($activeRole),
            'kpis' => $this->kpis(),
            'revenueEvolution' => $revenueEvolution,
            'topServices' => $topServices,
            'providerRevenue' => $this->providerRevenue(),
            'activeAlerts' => $this->activeAlerts(),
            'stripStats' => $this->stripStats($revenueEvolution, $topServices),
            'dataQuality' => $this->dataQuality(),
        ]);
    }

    public function placeholder(Request $request): View
    {
        $activeRole = $this->activeRole($request);

        return view('dashboard.placeholder', [
            'activeRole' => $activeRole,
            'roleLabel' => $this->roleLabel($activeRole),
            'title' => $request->route('title') ?? 'Module',
        ]);
    }

    private function activeRole(Request $request): string
    {
        return User::normalizeRole($request->session()->get('active_role')
            ?? $request->user()?->role
            ?? User::ROLE_BUSINESS);
    }

    private function roleLabel(string $role): string
    {
        return [
            User::ROLE_BUSINESS => 'Analyste Business',
            User::ROLE_OPERATIONAL => 'Analyste Operationnel',
            User::ROLE_ADMIN => 'Administrateur',
        ][$role] ?? 'Utilisateur';
    }

    private function kpis(): array
    {
        $totalRevenue = $this->safeFloat(fn () => DB::table('RA_T_AGG_OCC')
            ->whereRaw('START_DATE >= (SELECT TRUNC(MAX(START_DATE)) - 29 FROM RA_T_AGG_OCC)')
            ->whereRaw('START_DATE < (SELECT TRUNC(MAX(START_DATE)) + 1 FROM RA_T_AGG_OCC)')
            ->sum('CHARGE_AMOUNT'));
        $totalSms = $this->safeInt(fn () => DB::table('RA_T_AGG_OCC')
            ->whereRaw('START_DATE >= (SELECT TRUNC(MAX(START_DATE)) - 29 FROM RA_T_AGG_OCC)')
            ->whereRaw('START_DATE < (SELECT TRUNC(MAX(START_DATE)) + 1 FROM RA_T_AGG_OCC)')
            ->sum('CDR_COUNT'));
        $services = $this->safeInt(fn () => Service::query()->count());
        $alerts = count($this->activeAlerts());

        return [
            [
                'label' => 'Revenu total',
                'value' => $this->formatNumber($totalRevenue),
                'unit' => 'TND',
                'trend' => $this->trend('revenue'),
                'description' => 'Revenus OCC sur 30 jours',
                'progress' => $this->progress($totalRevenue),
                'icon' => 'bi-cash-stack',
                'tone' => 'teal',
            ],
            [
                'label' => 'Nombre total de SMS+',
                'value' => $this->formatNumber($totalSms),
                'unit' => 'SMS',
                'trend' => $this->trend('sms'),
                'description' => 'Trafic SMS+ OCC traité',
                'progress' => $this->progress($totalSms),
                'icon' => 'bi-chat-dots',
                'tone' => 'blue',
            ],
            [
                'label' => 'Nombre de services',
                'value' => $this->formatNumber($services),
                'unit' => 'actifs',
                'trend' => 'Oracle',
                'description' => 'Services VAS actifs',
                'progress' => $this->progress($services, 100),
                'icon' => 'bi-grid',
                'tone' => 'amber',
            ],
            [
                'label' => 'Alertes actives',
                'value' => $this->formatNumber($alerts),
                'unit' => 'alertes',
                'trend' => 'Oracle',
                'description' => 'Incidents ouverts',
                'progress' => $this->progress($alerts, 20),
                'icon' => 'bi-exclamation-triangle',
                'tone' => 'red',
            ],
        ];
    }

    private function revenueEvolution(): array
    {
        $dailyRows = $this->safeArray(fn () => DB::table('ra_t_agg_occ')
            ->select('START_DATE', DB::raw('SUM(CHARGE_AMOUNT) AS REVENUE'))
            ->whereNotNull('START_DATE')
            ->groupBy('START_DATE')
            ->orderByDesc('START_DATE')
            ->limit(7)
            ->get()
            ->map(fn (object $row): array => [
                'label' => $this->formatDateLabel($this->objectValue($row, 'START_DATE'), 'd M'),
                'sort' => $this->dateSortKey($this->objectValue($row, 'START_DATE')),
                'value' => (float) $this->objectValue($row, 'REVENUE'),
            ])
            ->sortBy('sort')
            ->values()
            ->all());

        $monthlyRows = $this->safeArray(fn () => DB::table('ra_t_agg_occ')
            ->select(
                DB::raw("TO_CHAR(START_DATE, 'YYYY-MM') AS MONTH_KEY"),
                DB::raw("TO_CHAR(START_DATE, 'Mon') AS MONTH_LABEL"),
                DB::raw('SUM(CHARGE_AMOUNT) AS REVENUE')
            )
            ->whereNotNull('START_DATE')
            ->groupBy(DB::raw("TO_CHAR(START_DATE, 'YYYY-MM')"), DB::raw("TO_CHAR(START_DATE, 'Mon')"))
            ->orderByDesc(DB::raw("TO_CHAR(START_DATE, 'YYYY-MM')"))
            ->limit(6)
            ->get()
            ->map(fn (object $row): array => [
                'label' => trim((string) $this->objectValue($row, 'MONTH_LABEL')),
                'sort' => (string) $this->objectValue($row, 'MONTH_KEY'),
                'value' => (float) $this->objectValue($row, 'REVENUE'),
            ])
            ->sortBy('sort')
            ->values()
            ->all());

        return [
            'daily' => [
                'labels' => array_column($dailyRows, 'label') ?: ['--'],
                'values' => array_column($dailyRows, 'value') ?: [0],
            ],
            'monthly' => [
                'labels' => array_column($monthlyRows, 'label') ?: ['--'],
                'values' => array_column($monthlyRows, 'value') ?: [0],
            ],
        ];
    }

    private function topServices(): array
    {
        $occRows = $this->safeArray(fn () => collect(DB::select("
            WITH service_revenue AS (
                SELECT
                    TRIM(B_MSISDN) AS SHORT_CODE,
                    NVL(SUM(CHARGE_AMOUNT), 0) AS REVENUE,
                    NVL(SUM(CDR_COUNT), 0) AS SMS_COUNT,
                    NVL(MAX(KEYWORD), 'Non renseigné') AS KEYWORD
                FROM RA_T_AGG_OCC
                WHERE START_DATE IS NOT NULL
                    AND B_MSISDN IS NOT NULL
                    AND START_DATE >= (SELECT TRUNC(MAX(START_DATE)) - 29 FROM RA_T_AGG_OCC)
                    AND START_DATE < (SELECT TRUNC(MAX(START_DATE)) + 1 FROM RA_T_AGG_OCC)
                GROUP BY TRIM(B_MSISDN)
                HAVING NVL(SUM(CHARGE_AMOUNT), 0) > 0
            ),
            services_by_code AS (
                SELECT
                    TRIM(SHORT_CODE) AS SHORT_CODE,
                    MAX(SERVICE_NAME) AS SERVICE_NAME,
                    MAX(PROVIDER_ID) AS PROVIDER_ID
                FROM SERVICES
                WHERE SHORT_CODE IS NOT NULL
                GROUP BY TRIM(SHORT_CODE)
            ),
            services_by_keyword AS (
                SELECT
                    LOWER(TRIM(KEYWORD)) AS KEYWORD,
                    MAX(SERVICE_NAME) AS SERVICE_NAME,
                    MAX(PROVIDER_ID) AS PROVIDER_ID
                FROM SERVICES
                WHERE KEYWORD IS NOT NULL
                GROUP BY LOWER(TRIM(KEYWORD))
            )
            SELECT *
            FROM (
                SELECT
                    sr.SHORT_CODE,
                    COALESCE(sc.SERVICE_NAME, sk.SERVICE_NAME, sr.SHORT_CODE, sr.KEYWORD) AS SERVICE_NAME,
                    NVL(p.PROVIDER_NAME, 'Non renseigné') AS PROVIDER_NAME,
                    sr.REVENUE,
                    sr.SMS_COUNT
                FROM service_revenue sr
                LEFT JOIN services_by_code sc ON sc.SHORT_CODE = sr.SHORT_CODE
                LEFT JOIN services_by_keyword sk ON sk.KEYWORD = LOWER(TRIM(sr.KEYWORD))
                LEFT JOIN SERVICE_PROVIDER p ON p.ID = COALESCE(sc.PROVIDER_ID, sk.PROVIDER_ID)
                ORDER BY sr.REVENUE DESC
            )
            WHERE ROWNUM <= 5
        "))->map(fn (object $row): array => [
            'name' => (string) $this->objectValue($row, 'SERVICE_NAME'),
            'provider' => (string) $this->objectValue($row, 'PROVIDER_NAME'),
            'revenue' => (float) $this->objectValue($row, 'REVENUE'),
            'sms' => (int) $this->objectValue($row, 'SMS_COUNT'),
        ])->all());

        if ($occRows) {
            return $occRows;
        }

        return $this->safeArray(fn () => DB::table('ra_t_agg_mmg')
            ->select(
                DB::raw("COALESCE(SERVICE_TYPE, 'N/A') AS SERVICE_NAME"),
                DB::raw("'CDR MMG' AS PROVIDER_NAME"),
                DB::raw('0 AS REVENUE'),
                DB::raw('SUM(CDR_COUNT) AS SMS_COUNT')
            )
            ->groupBy(DB::raw("COALESCE(SERVICE_TYPE, 'N/A')"))
            ->orderByDesc(DB::raw('SUM(CDR_COUNT)'))
            ->limit(5)
            ->get()
            ->map(fn (object $row): array => [
                'name' => (string) $this->objectValue($row, 'SERVICE_NAME'),
                'provider' => (string) $this->objectValue($row, 'PROVIDER_NAME'),
                'revenue' => (float) $this->objectValue($row, 'REVENUE'),
                'sms' => (int) $this->objectValue($row, 'SMS_COUNT'),
            ])
            ->all()) ?: [
                ['name' => 'Aucune donnee', 'provider' => '--', 'revenue' => 0, 'sms' => 0],
            ];
    }

    private function providerRevenue(): array
    {
        $rows = $this->safeArray(fn () => DB::select("
            WITH service_revenue AS (
                SELECT
                    TRIM(B_MSISDN) AS SHORT_CODE,
                    LOWER(TRIM(MAX(KEYWORD))) AS KEYWORD,
                    NVL(SUM(CHARGE_AMOUNT), 0) AS REVENUE
                FROM RA_T_AGG_OCC
                WHERE START_DATE IS NOT NULL
                    AND START_DATE >= (SELECT TRUNC(MAX(START_DATE)) - 29 FROM RA_T_AGG_OCC)
                    AND START_DATE < (SELECT TRUNC(MAX(START_DATE)) + 1 FROM RA_T_AGG_OCC)
                GROUP BY TRIM(B_MSISDN)
            ),
            services_by_code AS (
                SELECT
                    TRIM(SHORT_CODE) AS SHORT_CODE,
                    MAX(PROVIDER_ID) AS PROVIDER_ID
                FROM SERVICES
                WHERE SHORT_CODE IS NOT NULL
                GROUP BY TRIM(SHORT_CODE)
            ),
            services_by_keyword AS (
                SELECT
                    LOWER(TRIM(KEYWORD)) AS KEYWORD,
                    MAX(PROVIDER_ID) AS PROVIDER_ID
                FROM SERVICES
                WHERE KEYWORD IS NOT NULL
                GROUP BY LOWER(TRIM(KEYWORD))
            ),
            provider_data AS (
                SELECT
                    NVL(p.PROVIDER_NAME, 'Sans fournisseur') AS PROVIDER_NAME,
                    NVL(SUM(sr.REVENUE), 0) AS REVENUE
                FROM service_revenue sr
                LEFT JOIN services_by_code sc ON sc.SHORT_CODE = sr.SHORT_CODE
                LEFT JOIN services_by_keyword sk ON sk.KEYWORD = sr.KEYWORD
                LEFT JOIN SERVICE_PROVIDER p ON p.ID = COALESCE(sc.PROVIDER_ID, sk.PROVIDER_ID)
                GROUP BY NVL(p.PROVIDER_NAME, 'Sans fournisseur')
                ORDER BY REVENUE DESC
            )
            SELECT * FROM provider_data WHERE ROWNUM <= 5
        "));

        $total = max(1, array_sum(array_map(fn (object $row): float => (float) $this->objectValue($row, 'REVENUE'), $rows)));

        return [
            'labels' => array_map(fn (object $row): string => (string) $this->objectValue($row, 'PROVIDER_NAME'), $rows) ?: ['--'],
            'values' => array_map(fn (object $row): float => round(((float) $this->objectValue($row, 'REVENUE') / $total) * 100, 2), $rows) ?: [0],
            'revenues' => array_map(fn (object $row): float => (float) $this->objectValue($row, 'REVENUE'), $rows) ?: [0],
        ];
    }

    private function activeAlerts(): array
    {
        return $this->safeArray(fn () => DB::table('ALERTS')
            ->selectRaw('SERVICE_NAME, VARIATION_PERCENTAGE, STATUS, MOTIF, ALERT_DATE')
            ->whereRaw("LOWER(NVL(STATUS, 'investigating')) <> 'resolved'")
            ->orderBy('ALERT_DATE', 'desc')
            ->limit(5)
            ->get()
            ->map(fn (object $alert): array => [
                'severity' => $this->alertSeverity((float) $this->objectValue($alert, 'VARIATION_PERCENTAGE')),
                'message' => (string) ($this->objectValue($alert, 'MOTIF') ?: $this->objectValue($alert, 'SERVICE_NAME') ?: 'Alerte SMS+'),
                'time' => $this->relativeTime($this->objectValue($alert, 'ALERT_DATE')),
            ])
            ->all());
    }

    private function stripStats(array $revenueEvolution, array $topServices): array
    {
        $dailyValues = $revenueEvolution['daily']['values'];
        $totalRevenue = $this->safeFloat(fn () => DB::table('ra_t_agg_occ')->sum('CHARGE_AMOUNT'));
        $topRevenue = array_sum(array_column($topServices, 'revenue'));

        return [
            'daily_average' => $this->formatNumber(count($dailyValues) ? array_sum($dailyValues) / count($dailyValues) : 0) . ' TND',
            'top_share' => $totalRevenue > 0 ? number_format(($topRevenue / $totalRevenue) * 100, 1, ',', ' ') . '%' : '0%',
            'monthly_trend' => $this->trend('revenue'),
        ];
    }

    private function dataQuality(): array
    {
        $services = $this->safeInt(fn () => Service::query()->count());
        $providers = $this->safeInt(fn () => DB::table('service_provider')->count());
        $occKeywords = $this->safeInt(fn () => DB::table('ra_t_agg_occ')
            ->whereNotNull('KEYWORD')
            ->distinct()
            ->count('KEYWORD'));
        $matchedKeywords = $this->safeInt(fn () => DB::table('ra_t_agg_occ')
            ->join('services', DB::raw('LOWER(TRIM(services.KEYWORD))'), '=', DB::raw('LOWER(TRIM(ra_t_agg_occ.KEYWORD))'))
            ->whereNotNull('ra_t_agg_occ.KEYWORD')
            ->distinct()
            ->count('ra_t_agg_occ.KEYWORD'));

        return [
            'services' => $services,
            'providers' => $providers,
            'occ_keywords' => $occKeywords,
            'matched_keywords' => $matchedKeywords,
        ];
    }

    private function trend(string $metric): string
    {
        $column = $metric === 'revenue' ? 'CHARGE_AMOUNT' : 'CDR_COUNT';
        $table = 'ra_t_agg_occ';

        return $this->safeString(function () use ($table, $column): string {
            $latestDate = DB::table($table)->max('START_DATE');

            if (! $latestDate) {
                return '0%';
            }

            $latest = Carbon::parse((string) $latestDate);
            $current = (float) DB::table($table)
                ->whereRaw("START_DATE >= TO_DATE(?, 'YYYY-MM-DD')", [$latest->copy()->subDays(6)->format('Y-m-d')])
                ->sum($column);
            $previous = (float) DB::table($table)
                ->whereRaw("START_DATE >= TO_DATE(?, 'YYYY-MM-DD')", [$latest->copy()->subDays(13)->format('Y-m-d')])
                ->whereRaw("START_DATE < TO_DATE(?, 'YYYY-MM-DD')", [$latest->copy()->subDays(6)->format('Y-m-d')])
                ->sum($column);

            if ($previous <= 0) {
                return $current > 0 ? '+100%' : '0%';
            }

            $trend = (($current - $previous) / $previous) * 100;

            return ($trend >= 0 ? '+' : '') . number_format($trend, 1, ',', ' ') . '%';
        }, '0%');
    }

    private function progress(float|int $value, float|int $target = 1000000): int
    {
        return $target > 0 ? max(0, min(100, (int) round(($value / $target) * 100))) : 0;
    }

    private function formatNumber(float|int $value): string
    {
        return number_format((float) $value, 0, ',', ' ');
    }

    private function formatDateLabel(mixed $value, string $format): string
    {
        try {
            return Carbon::parse((string) $value)->format($format);
        } catch (Throwable) {
            return '--';
        }
    }

    private function dateSortKey(mixed $value): string
    {
        try {
            return Carbon::parse((string) $value)->format('Y-m-d');
        } catch (Throwable) {
            return (string) $value;
        }
    }

    private function relativeTime(mixed $value): string
    {
        if (! $value) {
            return '--';
        }

        try {
            return Carbon::parse((string) $value)->diffForHumans();
        } catch (Throwable) {
            return (string) $value;
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

    private function safeInt(callable $callback): int
    {
        try {
            return (int) $callback();
        } catch (Throwable) {
            return 0;
        }
    }

    private function safeFloat(callable $callback): float
    {
        try {
            return (float) $callback();
        } catch (Throwable) {
            return 0.0;
        }
    }

    private function safeArray(callable $callback): array
    {
        try {
            return (array) $callback();
        } catch (Throwable) {
            return [];
        }
    }

    private function safeString(callable $callback, string $fallback): string
    {
        try {
            return (string) $callback();
        } catch (Throwable) {
            return $fallback;
        }
    }

    private function alertSeverity(float $variation): string
    {
        $absolute = abs($variation);

        return match (true) {
            $absolute >= 30 => 'Critique',
            $absolute >= 20 => 'Majeure',
            $absolute >= 10 => 'Moyenne',
            default => 'Mineure',
        };
    }
}
