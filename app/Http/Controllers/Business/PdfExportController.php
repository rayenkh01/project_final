<?php

namespace App\Http\Controllers\Business;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class PdfExportController extends Controller
{
    public function exportPdf(Request $request): Response|View
    {
        $end = $this->latestOccDate();
        $start = $end->copy()->subDays(6)->startOfDay();
        $end = $end->endOfDay();

        $data = $this->buildReportData($start, $end);

        $pdfFacade = '\\Barryvdh\\DomPDF\\Facade\\Pdf';

        if (class_exists($pdfFacade)) {
            return $pdfFacade::loadView('business.alerts.report', $data)
                ->setPaper('a4', 'portrait')
                ->setOptions([
                    'defaultFont' => 'DejaVu Sans',
                    'isHtml5ParserEnabled' => true,
                    'isRemoteEnabled' => false,
                    'dpi' => 150,
                ])
                ->download('rapport-business-' . now()->format('Ymd-Hi') . '.pdf');
        }

        return view('business.alerts.report', $data + ['printFallback' => true]);
    }

    private function buildReportData(Carbon $start, Carbon $end): array
    {
        $mmgOcc = $this->mmgVsOcc($start, $end);
        $revenueTotal = $this->revenueTotal($start, $end);
        $previousRevenue = $this->revenueTotal($start->copy()->subDays(7), $end->copy()->subDays(7));
        $variation = $previousRevenue > 0
            ? round((($revenueTotal - $previousRevenue) / $previousRevenue) * 100, 1)
            : 0.0;

        return [
            'activeRole' => User::ROLE_BUSINESS,
            'roleLabel' => User::roleLabel(User::ROLE_BUSINESS),
            'generatedAt' => now(),
            'startOfWeek' => $start,
            'endOfWeek' => $end,
            'stats' => $this->stats($start, $end),
            'revenueTotal' => $revenueTotal,
            'variation' => $variation,
            'dailyAverage' => round($revenueTotal / 7, 2),
            'mmgOccLabels' => $mmgOcc['labels'],
            'mmgData' => $mmgOcc['mmg'],
            'occData' => $mmgOcc['occ'],
            'mmgTotal' => $mmgOcc['mmg_total'],
            'occTotal' => $mmgOcc['occ_total'],
            'gap' => $mmgOcc['gap'],
            'topServices' => $this->topServices($start, $end),
            'anomalies' => $this->anomalies($start, $end),
        ];
    }

    private function latestOccDate(): Carbon
    {
        try {
            $row = DB::table('RA_T_AGG_OCC')
                ->selectRaw('MAX(START_DATE) as latest_date')
                ->first();

            $latest = $this->value($row, 'latest_date');

            if ($latest) {
                return Carbon::parse($latest);
            }
        } catch (Throwable) {
            // Keep a usable report even when Oracle is temporarily unavailable.
        }

        return now()->subDay();
    }

    private function stats(Carbon $start, Carbon $end): array
    {
        return $this->safeArray(function () use ($start, $end): array {
            $range = "ALERT_DATE >= TO_DATE(?, 'YYYY-MM-DD') AND ALERT_DATE < TO_DATE(?, 'YYYY-MM-DD')";
            $params = [$start->format('Y-m-d'), $end->copy()->addDay()->format('Y-m-d')];
            $total = DB::table('ALERTS')->whereRaw($range, $params)->count();
            $critical = DB::table('ALERTS')->whereRaw($range, $params)->whereRaw('ABS(VARIATION_PERCENTAGE) >= 30')->count();
            $open = DB::table('ALERTS')->whereRaw($range, $params)->whereRaw("LOWER(NVL(STATUS, 'investigating')) <> 'resolved'")->count();

            return [
                'total' => (int) $total,
                'critical' => (int) $critical,
                'open' => (int) $open,
            ];
        }, ['total' => 0, 'critical' => 0, 'open' => 0]);
    }

    private function revenueTotal(Carbon $start, Carbon $end): float
    {
        try {
            return (float) DB::table('RA_T_AGG_OCC')
                ->whereRaw(
                    "START_DATE >= TO_DATE(?, 'YYYY-MM-DD') AND START_DATE < TO_DATE(?, 'YYYY-MM-DD')",
                    [$start->format('Y-m-d'), $end->copy()->addDay()->format('Y-m-d')]
                )
                ->sum('CHARGE_AMOUNT');
        } catch (Throwable) {
            return 0.0;
        }
    }

    private function mmgVsOcc(Carbon $start, Carbon $end): array
    {
        $labels = [];
        $mmg = [];
        $occ = [];

        for ($date = $start->copy(); $date->lte($end); $date->addDay()) {
            $dateKey = $date->format('Y-m-d');
            $labels[] = $date->format('d/m');

            $mmg[] = $this->sumCdrByDay('RA_T_AGG_MMG', $dateKey);
            $occ[] = $this->sumCdrByDay('RA_T_AGG_OCC', $dateKey);
        }

        $mmgTotal = array_sum($mmg);
        $occTotal = array_sum($occ);
        $gap = $mmgTotal > 0 ? round((($occTotal - $mmgTotal) / $mmgTotal) * 100, 1) : 0.0;

        return [
            'labels' => $labels,
            'mmg' => $mmg,
            'occ' => $occ,
            'mmg_total' => $mmgTotal,
            'occ_total' => $occTotal,
            'gap' => $gap,
        ];
    }

    private function sumCdrByDay(string $table, string $dateKey): int
    {
        try {
            return (int) DB::table($table)
                ->whereRaw("TRUNC(START_DATE) = TO_DATE(?, 'YYYY-MM-DD')", [$dateKey])
                ->sum('CDR_COUNT');
        } catch (Throwable) {
            return 0;
        }
    }

    private function topServices(Carbon $start, Carbon $end): array
    {
        return $this->safeArray(function () use ($start, $end): array {
            return DB::table('RA_T_AGG_OCC as occ')
                ->leftJoin('SERVICES as svc', DB::raw('TRIM(svc.SHORT_CODE)'), '=', DB::raw('TRIM(occ.B_MSISDN)'))
                ->leftJoin('SERVICE_PROVIDER as sp', 'sp.ID', '=', 'svc.PROVIDER_ID')
                ->selectRaw("
                    TRIM(occ.B_MSISDN) as B_MSISDN,
                    COALESCE(MAX(svc.SERVICE_NAME), NULLIF(TRIM(occ.KEYWORD), ''), TRIM(occ.B_MSISDN)) as SERVICE_NAME,
                    MAX(sp.PROVIDER_NAME) as PROVIDER_NAME,
                    SUM(occ.CHARGE_AMOUNT) as REVENUE,
                    SUM(occ.CDR_COUNT) as SMS_COUNT
                ")
                ->whereRaw(
                    "occ.START_DATE >= TO_DATE(?, 'YYYY-MM-DD') AND occ.START_DATE < TO_DATE(?, 'YYYY-MM-DD')",
                    [$start->format('Y-m-d'), $end->copy()->addDay()->format('Y-m-d')]
                )
                ->groupByRaw("TRIM(occ.B_MSISDN), NULLIF(TRIM(occ.KEYWORD), '')")
                ->orderByRaw('SUM(occ.CHARGE_AMOUNT) DESC')
                ->limit(5)
                ->get()
                ->map(fn (object $row): array => [
                    'name' => (string) ($this->value($row, 'service_name') ?: $this->value($row, 'b_msisdn') ?: 'Service SMS+'),
                    'provider' => (string) ($this->value($row, 'provider_name') ?: 'N/A'),
                    'revenue' => (float) $this->value($row, 'revenue'),
                    'sms' => (int) $this->value($row, 'sms_count'),
                ])
                ->all();
        });
    }

    private function anomalies(Carbon $start, Carbon $end): array
    {
        return $this->safeArray(function () use ($start, $end): array {
            return DB::table('ALERTS')
                ->whereRaw(
                    "ALERT_DATE >= TO_DATE(?, 'YYYY-MM-DD') AND ALERT_DATE < TO_DATE(?, 'YYYY-MM-DD')",
                    [$start->format('Y-m-d'), $end->copy()->addDay()->format('Y-m-d')]
                )
                ->orderBy('ALERT_DATE', 'desc')
                ->limit(10)
                ->get()
                ->map(fn (object $row): array => [
                    'date' => $this->formatDate($this->value($row, 'alert_date')),
                    'service' => (string) ($this->value($row, 'service_name') ?: 'Service SMS+'),
                    'motif' => (string) ($this->value($row, 'motif') ?: 'Anomalie detectee'),
                    'variation' => (float) ($this->value($row, 'variation_percentage') ?? 0),
                    'sms' => (int) ($this->value($row, 'sms_count') ?? 0),
                    'status' => (string) ($this->value($row, 'status') ?: 'investigating'),
                ])
                ->all();
        });
    }

    private function formatDate(mixed $value): string
    {
        try {
            return Carbon::parse((string) $value)->format('d/m/Y H:i');
        } catch (Throwable) {
            return '--';
        }
    }

    private function value(?object $row, string $key): mixed
    {
        if ($row === null) {
            return null;
        }

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
