<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nouveau mot de passe - VAS SMS+ Monitor</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: Arial, sans-serif; }
        body {
            min-height: 100vh;
            background: linear-gradient(135deg, #0e9aa7, #10b3b8);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .wrapper { width: 100%; max-width: 420px; text-align: center; }
        .card {
            background: #ffffff;
            border-radius: 18px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.18);
            padding: 28px;
            text-align: left;
        }
        .title-box { text-align: center; margin-bottom: 20px; }
        .title-box img { width: 160px; margin-bottom: 10px; }
        .title-box h1 { font-size: 22px; color: #1f2937; margin-bottom: 4px; }
        .title-box p { font-size: 12px; color: #6b7280; }
        .form-group { margin-bottom: 16px; }
        .main-label { display: block; font-size: 12px; font-weight: bold; color: #4b5563; margin-bottom: 8px; }
        input {
            width: 100%;
            border: 1px solid #d1d5db;
            border-radius: 10px;
            padding: 12px 14px;
            font-size: 14px;
            outline: none;
        }
        input:focus { border-color: #0ea5e9; box-shadow: 0 0 0 3px rgba(14, 165, 233, 0.15); }
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
            margin-bottom: 14px;
        }
        .link { display: block; text-align: center; color: #0284c7; text-decoration: none; font-size: 13px; }
        .link:hover { text-decoration: underline; }
        .alert {
            padding: 12px;
            border-radius: 10px;
            font-size: 13px;
            margin-bottom: 16px;
        }
        .alert-danger { background: #fef2f2; border: 1px solid #fecaca; color: #b91c1c; }
    </style>
</head>
<body>
    <div class="wrapper">
        <div class="card">
            <div class="title-box">
                <img src="{{ asset('images/logo_TT.png') }}" alt="Logo">
                <h1>Nouveau mot de passe</h1>
                <p>Choisissez un mot de passe pour votre compte actif</p>
            </div>

            @if ($errors->any())
                <div class="alert alert-danger">
                    {{ $errors->first() }}
                </div>
            @endif

            <form method="POST" action="{{ route('password.update.custom') }}">
                @csrf
                <input type="hidden" name="token" value="{{ $token }}">

                <div class="form-group">
                    <label class="main-label" for="email">Email</label>
                    <input id="email" type="email" name="email" value="{{ old('email', $email) }}" required>
                </div>

                <div class="form-group">
                    <label class="main-label" for="password">Nouveau mot de passe</label>
                    <input id="password" type="password" name="password" minlength="6" required>
                </div>

                <div class="form-group">
                    <label class="main-label" for="password_confirmation">Confirmer le mot de passe</label>
                    <input id="password_confirmation" type="password" name="password_confirmation" minlength="6" required>
                </div>

                <button type="submit" class="btn">Enregistrer</button>
                <a class="link" href="{{ route('login') }}">Retour a la connexion</a>
            </form>
        </div>
    </div>
</body>
</html>
