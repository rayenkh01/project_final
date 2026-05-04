@php
    use App\Models\User;

    $activeRole = $activeRole ?? session('active_role', auth()->user()?->role ?? User::ROLE_BUSINESS);
    $roleLabel = $roleLabel ?? [
        User::ROLE_BUSINESS => 'Analyste Business',
        User::ROLE_OPERATIONAL => 'Analyste Opérationnel',
        User::ROLE_ADMIN => 'Administrateur',
    ][$activeRole] ?? 'Utilisateur';

    $menus = [
        User::ROLE_BUSINESS => [
            ['label' => 'Dashboard', 'route' => 'dashboard', 'active' => 'dashboard', 'icon' => 'bi-speedometer2'],
            ['label' => 'Recherche MSISDN', 'route' => 'business.msisdn.search', 'active' => 'business.msisdn.*', 'icon' => 'bi-search'],
            ['label' => 'Alertes / incidents', 'route' => 'business.alerts.index', 'active' => 'business.alerts.*', 'icon' => 'bi-bell'],
            ['label' => 'Notification email', 'route' => 'business.notifications.email', 'active' => 'business.notifications.*', 'icon' => 'bi-envelope'],
        ],
        User::ROLE_OPERATIONAL => [
            ['label' => 'Dashboard', 'route' => 'dashboard', 'active' => 'dashboard', 'icon' => 'bi-speedometer2'],
            ['label' => 'Loading CDR MMG/OCC', 'route' => 'operations.cdr.loading', 'active' => 'operations.cdr.loading', 'icon' => 'bi-cloud-arrow-up'],
            ['label' => 'Agrégation', 'route' => 'operations.aggregation.index', 'active' => 'operations.aggregation.*', 'icon' => 'bi-diagram-3'],
            ['label' => 'Suppression CDR', 'route' => 'operations.cdr.delete', 'active' => 'operations.cdr.delete', 'icon' => 'bi-trash3'],
            ['label' => 'Gestion des services', 'route' => 'operations.services.index', 'active' => 'operations.services.*', 'icon' => 'bi-sliders'],
            ['label' => 'Gestion fournisseurs', 'route' => 'operations.providers.index', 'active' => 'operations.providers.*', 'icon' => 'bi-building'],
        ],
        User::ROLE_ADMIN => [
            ['label' => 'Dashboard', 'route' => 'dashboard', 'active' => 'dashboard', 'icon' => 'bi-speedometer2'],
            ['label' => 'Gerer BD', 'route' => 'admin.database.index', 'active' => 'admin.database.*', 'icon' => 'bi-database'],
            ['label' => 'FTP', 'route' => 'admin.ftp.index', 'active' => 'admin.ftp.*', 'icon' => 'bi-hdd-network'],
            ['label' => 'Utilisateurs', 'route' => 'admin.users.index', 'active' => 'admin.users.*', 'icon' => 'bi-people'],
        ],
    ];

    $items = $menus[$activeRole] ?? $menus[User::ROLE_BUSINESS];
@endphp

<aside class="app-sidebar">
    <div class="sidebar-brand">
        <img src="{{ asset('images/logo_TT.png') }}" alt="Tunisie Telecom">
        <div>
            <strong>VAS SMS+ Monitor</strong>
            <span>Tunisie Telecom</span>
        </div>
    </div>

    <div class="px-3 pt-3">
        <div class="sidebar-role">
            Rôle connecté
            <div class="text-white fw-semibold mt-1">{{ $roleLabel }}</div>
        </div>
    </div>

    <nav class="sidebar-nav">
        @foreach ($items as $item)
            <a
                class="sidebar-link {{ request()->routeIs($item['active']) ? 'active' : '' }}"
                href="{{ route($item['route']) }}"
            >
                <i class="bi {{ $item['icon'] }}"></i>
                <span>{{ $item['label'] }}</span>
            </a>
        @endforeach
    </nav>

    <div class="sidebar-footer mt-auto">
        <div class="small text-secondary">Plateforme revenus VAS/SMS+</div>
    </div>
</aside>
