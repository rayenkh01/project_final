<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>VAS SMS+ Monitor - Login</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: Arial, sans-serif;
        }

        body {
            min-height: 100vh;
            background: linear-gradient(135deg, #0e9aa7, #10b3b8);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .wrapper {
            width: 100%;
            max-width: 420px;
            text-align: center;
        }

        .card {
            background: #ffffff;
            border-radius: 18px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.18);
            padding: 28px;
            text-align: left;
        }

        .logo {
            width: 72px;
            height: 72px;
            border-radius: 50%;
            margin: 0 auto 14px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .title-box {
            text-align: center;
            margin-bottom: 20px;
        }

        .title-box h1 {
            font-size: 22px;
            color: #1f2937;
            margin-bottom: 4px;
        }

        .title-box p {
            font-size: 12px;
            color: #6b7280;
        }

        .form-group {
            margin-bottom: 16px;
        }

        .form-group label.main-label {
            display: block;
            font-size: 12px;
            font-weight: bold;
            color: #4b5563;
            margin-bottom: 8px;
        }

        .role-option {
            display: flex;
            gap: 10px;
            border: 1px solid #d1d5db;
            border-radius: 10px;
            padding: 12px;
            margin-bottom: 10px;
            cursor: pointer;
            transition: 0.2s ease;
        }

        .role-option:hover {
            border-color: #0ea5e9;
        }

        .role-option.active {
            border: 2px solid #2563eb;
            background: #eff6ff;
        }

        .role-option input {
            margin-top: 4px;
        }

        .role-text strong {
            display: block;
            font-size: 14px;
            color: #111827;
            margin-bottom: 2px;
        }

        .role-text span {
            font-size: 12px;
            color: #6b7280;
        }

        input[type="email"],
        input[type="password"] {
            width: 100%;
            border: 1px solid #d1d5db;
            border-radius: 10px;
            padding: 12px 14px;
            font-size: 14px;
            outline: none;
        }

        input[type="email"]:focus,
        input[type="password"]:focus {
            border-color: #0ea5e9;
            box-shadow: 0 0 0 3px rgba(14, 165, 233, 0.15);
        }

        .row-between {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 12px;
            color: #6b7280;
            margin-bottom: 18px;
        }

        .row-between a {
            color: #0284c7;
            text-decoration: none;
        }

        .row-between a:hover {
            text-decoration: underline;
        }

        .btn {
            width: 100%;
            border: none;
            border-radius: 10px;
            padding: 13px;
            color: white;
            font-weight: bold;
            font-size: 14px;
            cursor: pointer;
            background: linear-gradient(90deg, #2563eb, #06b6d4);
            margin-bottom: 16px;
        }

        .btn:hover {
            opacity: 0.95;
        }

        .demo-box {
            background: #eff6ff;
            border: 1px solid #bfdbfe;
            border-radius: 10px;
            padding: 12px;
            font-size: 12px;
            color: #475569;
            line-height: 1.6;
        }

        .alert {
            padding: 12px;
            border-radius: 10px;
            font-size: 13px;
            margin-bottom: 16px;
        }

        .alert-danger {
            background: #fef2f2;
            border: 1px solid #fecaca;
            color: #b91c1c;
        }

        .alert-success {
            background: #ecfdf5;
            border: 1px solid #bbf7d0;
            color: #047857;
        }

        .footer {
            margin-top: 18px;
            color: rgba(255, 255, 255, 0.95);
            font-size: 12px;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="wrapper">
        <div class="card">
            <div class="title-box">
                <div class="logo">
                    <img src="{{ asset('images/logo_TT.png') }}" alt="Logo" style="width:160px;">
                </div>
                <h1>VAS SMS+ Monitor</h1>
                <p>Revenue Analytics Platform</p>
            </div>

            @if ($errors->any())
                <div class="alert alert-danger">
                    {{ $errors->first() }}
                </div>
            @endif

            @if (session('status'))
                <div class="alert alert-success">
                    {{ session('status') }}
                </div>
            @endif

            <form method="POST" action="{{ route('login.submit') }}">
                @csrf

                <div class="form-group">
                    <label class="main-label">Type d'utilisateur</label>

                    <label class="role-option {{ old('role', 'business') == 'admin' ? 'active' : '' }}">
                        <input type="radio" name="role" value="admin" {{ old('role') == 'admin' ? 'checked' : '' }}>
                        <div class="role-text">
                            <strong>Administrateur</strong>
                            <span>Gestion complète du système</span>
                        </div>
                    </label>

                    <label class="role-option {{ old('role', 'business') == 'business' ? 'active' : '' }}">
                        <input type="radio" name="role" value="business" {{ old('role', 'business') == 'business' ? 'checked' : '' }}>
                        <div class="role-text">
                            <strong>Analyste Business</strong>
                            <span>Analyse revenus et rapports</span>
                        </div>
                    </label>

                    <label class="role-option {{ old('role') == 'operationnel' ? 'active' : '' }}">
                        <input type="radio" name="role" value="operationnel" {{ old('role') == 'operationnel' ? 'checked' : '' }}>
                        <div class="role-text">
                            <strong>Analyste Opérationnel</strong>
                            <span>Suivi CDR et alertes</span>
                        </div>
                    </label>
                </div>

                <div class="form-group">
                    <label class="main-label">Email Address</label>
                    <input type="email" name="email" value="{{ old('email') }}" placeholder="your.email@telecom.com" required>
                </div>

                <div class="form-group">
                    <div class="row-between" style="margin-bottom: 8px;">
                        <label class="main-label" style="margin-bottom: 0;">Password</label>
                        <a href="{{ route('password.request') }}">Forget password ?</a>
                    </div>
                    <input type="password" name="password" placeholder="Enter your password" required>
                </div>

                <button type="submit" class="btn">Sign In</button>

                <div class="demo-box">
                    Connexion via la table Oracle users.<br>
                    Pour une demo sans Oracle, activez AUTH_DEMO_LOGIN=true.
                </div>
            </form>
        </div>

        <div class="footer">
            © 2026 Tunisie Telecom. All rights reserved.
        </div>
    </div>
    <script>
        document.querySelectorAll('.role-option input').forEach((input) => {
            input.addEventListener('change', () => {
                document.querySelectorAll('.role-option').forEach((option) => option.classList.remove('active'));
                input.closest('.role-option').classList.add('active');
            });
        });
    </script>
</body>
</html>
