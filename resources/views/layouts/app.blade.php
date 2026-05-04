<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Dashboard') - VAS SMS+ Monitor</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        :root {
            --sidebar-width: 286px;
            --sidebar-bg: #111827;
            --sidebar-bg-soft: #1f2937;
            --page-bg: #f4f7fb;
            --text-main: #1f2937;
            --text-muted: #6b7280;
            --accent: #00a6b2;
            --border: #e5e7eb;
        }

        body {
            min-height: 100vh;
            background: var(--page-bg);
            color: var(--text-main);
            font-family: Inter, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            letter-spacing: 0;
        }

        .app-sidebar {
            position: fixed;
            inset: 0 auto 0 0;
            width: var(--sidebar-width);
            background: linear-gradient(180deg, var(--sidebar-bg), #0b1220);
            color: #fff;
            z-index: 1040;
            overflow-y: auto;
            transition: transform .2s ease;
        }

        .app-main {
            min-height: 100vh;
            margin-left: var(--sidebar-width);
        }

        .topbar {
            position: sticky;
            top: 0;
            z-index: 1020;
            background: rgba(244, 247, 251, .92);
            border-bottom: 1px solid var(--border);
            backdrop-filter: blur(12px);
        }

        .sidebar-brand {
            display: flex;
            gap: .75rem;
            align-items: center;
            padding: 1.35rem 1.25rem 1rem;
            border-bottom: 1px solid rgba(255, 255, 255, .08);
        }

        .sidebar-brand img {
            width: 46px;
            height: 46px;
            object-fit: contain;
            border-radius: 12px;
            background: #fff;
            padding: .25rem;
        }

        .sidebar-brand strong {
            display: block;
            font-size: 1rem;
            line-height: 1.1;
        }

        .sidebar-brand span,
        .sidebar-role {
            color: #cbd5e1;
            font-size: .78rem;
        }

        .sidebar-nav {
            padding: 1rem .8rem;
        }

        .sidebar-link {
            display: flex;
            align-items: center;
            gap: .75rem;
            color: #cbd5e1;
            text-decoration: none;
            border-radius: 8px;
            padding: .75rem .85rem;
            margin-bottom: .25rem;
            font-size: .94rem;
            transition: background-color .18s ease, color .18s ease, transform .18s ease;
        }

        .sidebar-link:hover,
        .sidebar-link.active {
            color: #fff;
            background: rgba(0, 166, 178, .18);
        }

        .sidebar-link.active {
            box-shadow: inset 3px 0 0 var(--accent);
        }

        .sidebar-link i {
            width: 1.25rem;
            color: #67e8f9;
        }

        .sidebar-footer {
            padding: 1rem 1.25rem 1.35rem;
            border-top: 1px solid rgba(255, 255, 255, .08);
        }

        .content-wrap {
            padding: 1.5rem;
        }

        .page-title {
            font-weight: 750;
            margin: 0;
        }

        .soft-card {
            background: #fff;
            border: 1px solid var(--border);
            border-radius: 8px;
            box-shadow: 0 8px 22px rgba(15, 23, 42, .04);
        }

        .kpi-card {
            min-height: 174px;
            padding: 1.2rem;
            position: relative;
            overflow: hidden;
        }

        .kpi-card::after {
            content: "";
            position: absolute;
            inset: auto 0 0 0;
            height: 4px;
            background: linear-gradient(90deg, #00a6b2, #2563eb);
        }

        .kpi-icon {
            width: 44px;
            height: 44px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 8px;
            font-size: 1.2rem;
        }

        .kpi-icon.teal { background: #ccfbf1; color: #0f766e; }
        .kpi-icon.blue { background: #dbeafe; color: #1d4ed8; }
        .kpi-icon.amber { background: #fef3c7; color: #b45309; }
        .kpi-icon.red { background: #fee2e2; color: #b91c1c; }

        .metric-progress {
            height: 7px;
            background: #eef2f7;
            border-radius: 999px;
            overflow: hidden;
        }

        .metric-progress span {
            display: block;
            height: 100%;
            border-radius: inherit;
            background: linear-gradient(90deg, #00a6b2, #2563eb);
        }

        .chart-box {
            min-height: 380px;
            padding: 1.15rem;
        }

        .chart-canvas-wrap {
            position: relative;
            width: 100%;
            height: 300px;
        }

        .chart-canvas-wrap.compact {
            height: 260px;
        }

        .stat-strip {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: .75rem;
        }

        .stat-strip-item {
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: .85rem;
            background: #f8fafc;
        }

        .chart-fallback {
            display: grid;
            gap: .75rem;
        }

        .fallback-row {
            display: grid;
            grid-template-columns: minmax(72px, 115px) 1fr minmax(58px, auto);
            gap: .75rem;
            align-items: center;
            font-size: .86rem;
        }

        .fallback-track {
            height: 12px;
            background: #eef2f7;
            border-radius: 999px;
            overflow: hidden;
        }

        .fallback-track span {
            display: block;
            height: 100%;
            border-radius: inherit;
            background: linear-gradient(90deg, #00a6b2, #2563eb);
        }

        .provider-list {
            display: grid;
            gap: .85rem;
        }

        .provider-dot {
            width: 10px;
            height: 10px;
            display: inline-block;
            border-radius: 999px;
        }

        .table-modern th {
            color: var(--text-muted);
            font-size: .78rem;
            font-weight: 700;
            text-transform: uppercase;
        }

        .badge-severity {
            border-radius: 999px;
            padding: .35rem .6rem;
            font-size: .74rem;
        }

        .sidebar-backdrop {
            display: none;
        }

        @media (max-width: 991.98px) {
            .app-sidebar {
                transform: translateX(-100%);
            }

            .sidebar-open .app-sidebar {
                transform: translateX(0);
            }

            .sidebar-open .sidebar-backdrop {
                display: block;
                position: fixed;
                inset: 0;
                background: rgba(15, 23, 42, .48);
                z-index: 1030;
            }

            .app-main {
                margin-left: 0;
            }

            .content-wrap {
                padding: 1rem;
            }

            .chart-canvas-wrap,
            .chart-canvas-wrap.compact {
                height: 250px;
            }

            .stat-strip {
                grid-template-columns: 1fr;
            }

            .fallback-row {
                grid-template-columns: 1fr;
                gap: .35rem;
            }
        }
    </style>
    @stack('styles')
</head>
<body>
    <div class="app-shell">
        @include('partials.sidebar')

        <button type="button" class="sidebar-backdrop border-0" data-sidebar-toggle aria-label="Fermer le menu"></button>

        <main class="app-main">
            <header class="topbar">
                <div class="container-fluid py-3 px-3 px-lg-4 d-flex align-items-center justify-content-between gap-3">
                    <div class="d-flex align-items-center gap-3">
                        <button class="btn btn-outline-secondary d-lg-none" type="button" data-sidebar-toggle aria-label="Ouvrir le menu">
                            <i class="bi bi-list"></i>
                        </button>
                        <div>
                            <h1 class="page-title fs-4">@yield('page-title', 'Dashboard')</h1>
                            <div class="text-muted small">{{ $roleLabel ?? 'Utilisateur' }}</div>
                        </div>
                    </div>

                    <div class="d-flex align-items-center gap-2">
                        <span class="badge text-bg-light border d-none d-sm-inline-flex align-items-center gap-2 py-2">
                            <i class="bi bi-database-check text-success"></i>
                            Oracle ready
                        </span>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button class="btn btn-dark btn-sm" type="submit">
                                <i class="bi bi-box-arrow-right me-1"></i>
                                Déconnexion
                            </button>
                        </form>
                    </div>
                </div>
            </header>

            <div class="content-wrap">
                @yield('content')
            </div>
        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.querySelectorAll('[data-sidebar-toggle]').forEach((button) => {
            button.addEventListener('click', () => {
                document.body.classList.toggle('sidebar-open');
            });
        });
    </script>
    @stack('scripts')
</body>
</html>
