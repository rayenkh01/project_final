<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    /**
     * Dashboard commun aux rôles Business, Opérationnel et Administrateur.
     */
    public function index(Request $request): View
    {
        return view('dashboard.index', [
            'activeRole' => $this->activeRole($request),
            'roleLabel' => $this->roleLabel($this->activeRole($request)),
            'kpis' => $this->kpis(),
            'revenueEvolution' => $this->revenueEvolution(),
            'topServices' => $this->topServices(),
            'providerRevenue' => $this->providerRevenue(),
            'activeAlerts' => $this->activeAlerts(),
        ]);
    }

    public function placeholder(Request $request): View
    {
        $title = $request->route('title') ?? 'Module';

        return view('dashboard.placeholder', [
            'activeRole' => $this->activeRole($request),
            'roleLabel' => $this->roleLabel($this->activeRole($request)),
            'title' => $title,
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
            User::ROLE_OPERATIONAL => 'Analyste Opérationnel',
            User::ROLE_ADMIN => 'Administrateur',
        ][$role] ?? 'Utilisateur';
    }

    /**
     * Données statiques temporaires, à remplacer par des agrégations Oracle.
     *
     * Sources prévues :
     * - ra_t_agg_mmg / ra_t_agg_occ pour revenus et volumes SMS+
     * - services pour le nombre de services
     * - alerts pour les alertes actives
     */
    private function kpis(): array
    {
        return [
            [
                'label' => 'Revenu total',
                'value' => '1 284 750',
                'unit' => 'TND',
                'trend' => '+12,4%',
                'description' => 'Revenus MMG + OCC',
                'progress' => 82,
                'icon' => 'bi-cash-stack',
                'tone' => 'teal',
            ],
            [
                'label' => 'Nombre total de SMS+',
                'value' => '8 942 310',
                'unit' => 'SMS',
                'trend' => '+8,1%',
                'description' => 'Trafic SMS+ traite',
                'progress' => 74,
                'icon' => 'bi-chat-dots',
                'tone' => 'blue',
            ],
            [
                'label' => 'Nombre de services',
                'value' => '126',
                'unit' => 'actifs',
                'trend' => '+4',
                'description' => 'Services VAS actifs',
                'progress' => 68,
                'icon' => 'bi-grid',
                'tone' => 'amber',
            ],
            [
                'label' => 'Alertes actives',
                'value' => '7',
                'unit' => 'alertes',
                'trend' => '-2',
                'description' => 'Incidents ouverts',
                'progress' => 28,
                'icon' => 'bi-exclamation-triangle',
                'tone' => 'red',
            ],
        ];
    }

    private function revenueEvolution(): array
    {
        return [
            'daily' => [
                'labels' => ['01 Avr', '02 Avr', '03 Avr', '04 Avr', '05 Avr', '06 Avr', '07 Avr'],
                'values' => [145000, 152400, 148900, 168200, 173500, 181300, 190800],
            ],
            'monthly' => [
                'labels' => ['Nov', 'Déc', 'Jan', 'Fév', 'Mar', 'Avr'],
                'values' => [980000, 1045000, 1098000, 1152000, 1219000, 1284750],
            ],
        ];
    }

    private function topServices(): array
    {
        return [
            ['name' => 'Quiz SMS+', 'provider' => 'Digital Value', 'revenue' => 236500, 'sms' => 1245300],
            ['name' => 'Jeux Mobile', 'provider' => 'MobiFun', 'revenue' => 198400, 'sms' => 1092000],
            ['name' => 'Astuces Santé', 'provider' => 'Care Plus', 'revenue' => 151700, 'sms' => 846200],
            ['name' => 'Foot Live', 'provider' => 'Sport Media', 'revenue' => 133900, 'sms' => 729400],
            ['name' => 'Horoscope', 'provider' => 'Star Content', 'revenue' => 118300, 'sms' => 664900],
        ];
    }

    private function providerRevenue(): array
    {
        return [
            'labels' => ['Digital Value', 'MobiFun', 'Care Plus', 'Sport Media', 'Star Content'],
            'values' => [32, 25, 18, 14, 11],
        ];
    }

    private function activeAlerts(): array
    {
        return [
            ['severity' => 'Critique', 'message' => 'Écart revenu MMG/OCC supérieur à 5%', 'time' => 'Il y a 12 min'],
            ['severity' => 'Majeure', 'message' => 'Retard chargement CDR OCC', 'time' => 'Il y a 35 min'],
            ['severity' => 'Mineure', 'message' => 'Baisse volume SMS+ service Horoscope', 'time' => 'Il y a 1 h'],
        ];
    }
}
