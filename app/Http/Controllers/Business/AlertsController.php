<?php

namespace App\Http\Controllers\Business;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Throwable;

class AlertsController extends Controller
{
    public function index(Request $request): View
    {
        $end = now()->endOfDay();
        $start = now()->subDays(6)->startOfDay();

        $this->syncWeeklyAlerts($start, $end);

        return view('business.alerts.index', [
            'activeRole' => User::ROLE_BUSINESS,
            'roleLabel' => User::roleLabel(User::ROLE_BUSINESS),
            'periodLabel' => $start->format('d/m/Y') . ' - ' . $end->format('d/m/Y'),
            'stats' => $this->stats($start, $end),
            'trafficComparison' => $this->trafficComparison($start, $end),
            'services' => $this->services($start, $end),
            'topTraffic' => $this->topTraffic($start, $end),
            'anomalies' => $this->anomalies($start, $end),
        ]);
    }

    private function syncWeeklyAlerts(Carbon $start, Carbon $end): void
    {
        try {
            $comparison = $this->trafficComparison($start, $end);
            $services = $this->services($start, $end);

            DB::transaction(function () use ($start, $end, $comparison, $services): void {
                DB::table('ALERTS')
                    ->whereRaw(
                        "ALERT_DATE >= TO_DATE(?, 'YYYY-MM-DD') AND ALERT_DATE < TO_DATE(?, 'YYYY-MM-DD')",
                        [$start->format('Y-m-d'), $end->copy()->addDay()->format('Y-m-d')]
                    )
                    ->where('MOTIF', 'like', 'AUTO:%')
                    ->delete();

                $rows = [];

                if ($comparison['has_gap']) {
                    $rows[] = [
                        'ALERT_DATE' => now(),
                        'SERVICE_NAME' => 'Ecart MMG/OCC',
                        'SHORT_CODE' => null,
                        'KEYWORD' => null,
                        'PROVIDER_NAME' => null,
                        'VARIATION_PERCENTAGE' => $this->clampSignedPercentage((float) $comparison['gap']),
                        'SMS_COUNT' => max($comparison['mmg_total'], $comparison['occ_total']),
                        'MOTIF' => 'AUTO: Ecart MMG/OCC superieur a 3% sur les 7 derniers jours',
                        'STATUS' => 'investigating',
                    ];
                }

                foreach ($services as $service) {
                    $growth = (float) ($service['growth'] ?? 0);

                    if (abs($growth) <= 20) {
                        continue;
                    }

                    $rows[] = [
                        'ALERT_DATE' => now(),
                        'SERVICE_NAME' => $service['name'],
                        'SHORT_CODE' => $service['short_code'] ?? null,
                        'KEYWORD' => $service['keyword'] ?? $service['b_msisdn'] ?? $service['name'],
                        'PROVIDER_NAME' => $service['provider'] ?? null,
                        'VARIATION_PERCENTAGE' => $this->clampSignedPercentage($growth),
                        'SMS_COUNT' => (int) $service['count'],
                        'MOTIF' => $growth >= 0
                            ? 'AUTO: Croissance du trafic service superieure a 20%'
                            : 'AUTO: Diminution du trafic service superieure a 20%',
                        'STATUS' => 'investigating',
                    ];
                }

                if ($rows !== []) {
                    DB::table('ALERTS')->insert($rows);
                }
            });
        } catch (Throwable) {
            // The dashboard must stay available even if the alert sync fails.
        }
    }

    private function stats(Carbon $start, Carbon $end): array
    {
        return $this->safeArray(function () use ($start, $end): array {
            $baseRange = "ALERT_DATE >= TO_DATE(?, 'YYYY-MM-DD') AND ALERT_DATE < TO_DATE(?, 'YYYY-MM-DD')";
            $params = [$start->format('Y-m-d'), $end->copy()->addDay()->format('Y-m-d')];

            $total = DB::table('ALERTS')->whereRaw($baseRange, $params)->count();
            $critical = DB::table('ALERTS')
                ->whereRaw($baseRange, $params)
                ->whereRaw('ABS(VARIATION_PERCENTAGE) >= 30')
                ->count();
            $open = DB::table('ALERTS')
                ->whereRaw($baseRange, $params)
                ->whereRaw("LOWER(NVL(STATUS, 'investigating')) <> 'resolved'")
                ->count();
            $resolvedToday = DB::table('ALERTS')
                ->whereRaw("LOWER(STATUS) = 'resolved'")
                ->whereRaw('TRUNC(ALERT_DATE) = TRUNC(SYSDATE)')
                ->count();

            return [
                'total' => (int) $total,
                'critical' => (int) $critical,
                'open' => (int) $open,
                'resolved_today' => (int) $resolvedToday,
                'resolution_rate' => $total > 0 ? round(($resolvedToday / $total) * 100, 1) : 0,
            ];
        }, [
            'total' => 0,
            'critical' => 0,
            'open' => 0,
            'resolved_today' => 0,
            'resolution_rate' => 0,
        ]);
    }

    private function trafficComparison(Carbon $start, Carbon $end): array
    {
        $labels = [];
        $mmg = [];
        $occ = [];

        try {
            for ($date = $start->copy(); $date->lte($end); $date->addDay()) {
                $dateKey = $date->format('Y-m-d');
                $labels[] = $date->format('d/m');
                $mmg[] = (int) DB::table('RA_T_AGG_MMG')
                    ->whereRaw("TRUNC(START_DATE) = TO_DATE(?, 'YYYY-MM-DD')", [$dateKey])
                    ->sum('CDR_COUNT');
                $occ[] = (int) DB::table('RA_T_AGG_OCC')
                    ->whereRaw("TRUNC(START_DATE) = TO_DATE(?, 'YYYY-MM-DD')", [$dateKey])
                    ->sum('CDR_COUNT');
            }
        } catch (Throwable) {
            $labels = ['J-6', 'J-5', 'J-4', 'J-3', 'J-2', 'J-1', 'J'];
            $mmg = [0, 0, 0, 0, 0, 0, 0];
            $occ = [0, 0, 0, 0, 0, 0, 0];
        }

        $mmgTotal = array_sum($mmg);
        $occTotal = array_sum($occ);
        $gap = $mmgTotal > 0 ? round((($occTotal - $mmgTotal) / $mmgTotal) * 100, 1) : 0;

        return [
            'labels' => $labels,
            'mmg' => $mmg,
            'occ' => $occ,
            'mmg_total' => $mmgTotal,
            'occ_total' => $occTotal,
            'gap' => $gap,
            'has_gap' => abs($gap) > 3,
        ];
    }

    private function services(Carbon $start, Carbon $end): array
    {
        return $this->safeArray(function () use ($start, $end): array {
            $previousStart = $start->copy()->subWeek();
            $previousEnd = $end->copy()->subWeek();

            $currentRows = $this->serviceTrafficRows($start, $end);
            $previousRows = collect($this->serviceTrafficRows($previousStart, $previousEnd))
                ->mapWithKeys(fn (array $row): array => [$row['b_msisdn'] => (int) $row['count']]);

            return collect($currentRows)
                ->map(function (array $row) use ($previousRows): array {
                    $previous = (int) ($previousRows[$row['b_msisdn']] ?? 0);
                    $current = (int) $row['count'];
                    $growth = $previous > 0
                        ? round((($current - $previous) / $previous) * 100, 1)
                        : ($current > 0 ? 100.0 : 0.0);

                    return $row + [
                        'growth' => $growth,
                        'alert' => abs($growth) > 20,
                        'direction' => $growth >= 0 ? 'up' : 'down',
                    ];
                })
                ->sortByDesc(fn (array $row): float => abs((float) $row['growth']))
                ->values()
                ->take(5)
                ->all();
        });
    }

    private function serviceTrafficRows(Carbon $start, Carbon $end): array
    {
        return DB::table('RA_T_AGG_OCC as occ')
            ->leftJoin('SERVICES as s', DB::raw('TRIM(s.SHORT_CODE)'), '=', DB::raw('TRIM(occ.B_MSISDN)'))
            ->leftJoin('SERVICE_PROVIDER as p', 'p.ID', '=', 's.PROVIDER_ID')
            ->selectRaw("
                TRIM(occ.B_MSISDN) as B_MSISDN,
                COALESCE(MAX(s.SERVICE_NAME), NULLIF(TRIM(occ.KEYWORD), ''), TRIM(occ.B_MSISDN)) as SERVICE_NAME,
                MAX(s.SHORT_CODE) as SHORT_CODE,
                MAX(occ.KEYWORD) as KEYWORD,
                MAX(p.PROVIDER_NAME) as PROVIDER_NAME,
                SUM(occ.CDR_COUNT) as SMS_COUNT
            ")
            ->whereRaw(
                "occ.START_DATE >= TO_DATE(?, 'YYYY-MM-DD') AND occ.START_DATE < TO_DATE(?, 'YYYY-MM-DD')",
                [$start->format('Y-m-d'), $end->copy()->addDay()->format('Y-m-d')]
            )
            ->groupByRaw("TRIM(occ.B_MSISDN), NULLIF(TRIM(occ.KEYWORD), '')")
            ->orderByRaw('SUM(occ.CDR_COUNT) DESC')
            ->limit(10)
            ->get()
            ->map(fn (object $row): array => [
                'b_msisdn' => (string) ($this->value($row, 'B_MSISDN') ?: ''),
                'name' => (string) ($this->value($row, 'SERVICE_NAME') ?: 'Service SMS+'),
                'short_code' => $this->value($row, 'SHORT_CODE'),
                'keyword' => $this->value($row, 'KEYWORD'),
                'provider' => (string) ($this->value($row, 'PROVIDER_NAME') ?: 'Non renseigne'),
                'count' => (int) $this->value($row, 'SMS_COUNT'),
            ])
            ->all();
    }

    private function topTraffic(Carbon $start, Carbon $end): array
    {
        $dates = [];

        for ($date = $start->copy(); $date->lte($end); $date->addDay()) {
            $dates[] = [
                'key' => $date->format('Y-m-d'),
                'label' => $date->format('d/m'),
            ];
        }

        $services = $this->services($start, $end);
        $series = [];

        foreach ($services as $service) {
            $daily = $this->safeArray(function () use ($service, $start, $end): array {
                return DB::table('RA_T_AGG_OCC as occ')
                    ->selectRaw("TO_CHAR(TRUNC(occ.START_DATE), 'YYYY-MM-DD') as DAY_KEY, SUM(occ.CDR_COUNT) as SMS_COUNT")
                    ->whereRaw(
                        "occ.START_DATE >= TO_DATE(?, 'YYYY-MM-DD') AND occ.START_DATE < TO_DATE(?, 'YYYY-MM-DD')",
                        [$start->format('Y-m-d'), $end->copy()->addDay()->format('Y-m-d')]
                    )
                    ->whereRaw(
                        'TRIM(occ.B_MSISDN) = ?',
                        [$service['b_msisdn']]
                    )
                    ->groupByRaw('TRUNC(occ.START_DATE)')
                    ->get()
                    ->mapWithKeys(fn (object $row): array => [
                        (string) $this->value($row, 'DAY_KEY') => (int) $this->value($row, 'SMS_COUNT'),
                    ])
                    ->all();
            });

            $series[] = [
                'name' => $service['name'],
                'data' => array_map(fn (array $date): int => (int) ($daily[$date['key']] ?? 0), $dates),
            ];
        }

        return [
            'labels' => array_column($dates, 'label'),
            'series' => $series,
        ];
    }

    private function anomalies(Carbon $start, Carbon $end): array
    {
        return $this->safeArray(function () use ($start, $end): array {
            return DB::table('ALERTS')
                ->selectRaw('ALERT_DATE, SERVICE_NAME, PROVIDER_NAME, VARIATION_PERCENTAGE, SMS_COUNT, MOTIF, STATUS')
                ->whereRaw(
                    "ALERT_DATE >= TO_DATE(?, 'YYYY-MM-DD') AND ALERT_DATE < TO_DATE(?, 'YYYY-MM-DD')",
                    [$start->format('Y-m-d'), $end->copy()->addDay()->format('Y-m-d')]
                )
                ->orderBy('ALERT_DATE', 'desc')
                ->limit(8)
                ->get()
                ->map(fn (object $row): array => [
                    'service' => (string) ($this->value($row, 'SERVICE_NAME') ?: 'Service SMS+'),
                    'provider' => (string) ($this->value($row, 'PROVIDER_NAME') ?: 'N/A'),
                    'variation' => (float) $this->value($row, 'VARIATION_PERCENTAGE'),
                    'sms' => (int) $this->value($row, 'SMS_COUNT'),
                    'motif' => (string) ($this->value($row, 'MOTIF') ?: 'Anomalie detectee'),
                    'status' => strtolower((string) ($this->value($row, 'STATUS') ?: 'investigating')),
                    'date' => $this->formatAlertDate($this->value($row, 'ALERT_DATE')),
                    'severity' => $this->severity((float) $this->value($row, 'VARIATION_PERCENTAGE')),
                ])
                ->all();
        });
    }

    private function severity(float $variation): string
    {
        return match (true) {
            abs($variation) >= 30 => 'Critique',
            abs($variation) >= 20 => 'Elevee',
            abs($variation) >= 10 => 'Moyenne',
            default => 'Faible',
        };
    }

    private function clampSignedPercentage(float $value): float
    {
        return max(-999.99, min(999.99, round($value, 2)));
    }

    private function formatAlertDate(mixed $value): string
    {
        try {
            return Carbon::parse((string) $value)->format('d/m/Y H:i');
        } catch (Throwable) {
            return '--';
        }
    }

    private function value(object $row, string $key): mixed
    {
        foreach ([$key, strtolower($key), strtoupper($key)] as $property) {
            if (property_exists($row, $property)) {
                return $row->{$property};
            }
        }

        return null;
    }

    private function safeArray(callable $callback, array $fallback = []): array
    {
        try {
            return (array) $callback();
        } catch (Throwable) {
            return $fallback;
        }
    }
}
