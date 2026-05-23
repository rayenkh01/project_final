<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Compte VAS CDR cree</title>
</head>
<body style="font-family: Arial, sans-serif; color: #1f2937; line-height: 1.5;">
    <h2 style="color: #0f5d73;">Activez votre compte VAS CDR</h2>

    <p>Bonjour,</p>

    <p>
        Un compte utilisateur a ete cree pour vous sur la plateforme VAS CDR.
        Pour finaliser l'activation, veuillez definir votre mot de passe initial.
    </p>

    <table cellpadding="6" cellspacing="0" style="border-collapse: collapse;">
        <tr>
            <td><strong>Email de connexion</strong></td>
            <td>{{ $email }}</td>
        </tr>
        <tr>
            <td><strong>Role</strong></td>
            <td>{{ $roleLabel }}</td>
        </tr>
        @if ($direction)
            <tr>
                <td><strong>Direction</strong></td>
                <td>{{ $direction }}</td>
            </tr>
        @endif
    </table>

    <p>
        Pour activer votre compte, utilisez le lien suivant :
        <br>
        <a href="{{ $activationUrl }}">{{ $activationUrl }}</a>
    </p>

    <p>
        Ce lien est personnel, utilisable une seule fois et expire le {{ $expiresAt->format('d/m/Y H:i') }}.
    </p>

    <p>
        Apres activation, vous pourrez vous connecter depuis :
        <br>
        <a href="{{ $loginUrl }}">{{ $loginUrl }}</a>
    </p>

    <p>Cordialement,<br>Administration VAS CDR</p>
</body>
</html>
