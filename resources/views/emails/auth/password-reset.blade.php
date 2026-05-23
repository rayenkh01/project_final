<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Reinitialisation du mot de passe VAS CDR</title>
</head>
<body style="font-family: Arial, sans-serif; color: #1f2937; line-height: 1.5;">
    <h2 style="color: #0f5d73;">Reinitialisation du mot de passe</h2>

    <p>Bonjour,</p>

    <p>
        Une demande de reinitialisation du mot de passe a ete recue pour le compte {{ $email }}.
    </p>

    <p>
        Pour choisir un nouveau mot de passe, utilisez le lien suivant :
        <br>
        <a href="{{ $resetUrl }}">{{ $resetUrl }}</a>
    </p>

    <p>
        Ce lien expire dans {{ $expiresInMinutes }} minutes. Si vous n'etes pas a l'origine de cette demande,
        vous pouvez ignorer ce message.
    </p>

    <p>Cordialement,<br>Administration VAS CDR</p>
</body>
</html>
