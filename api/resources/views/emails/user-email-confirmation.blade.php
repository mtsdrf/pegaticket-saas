<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
</head>
<body style="font-family: Arial, sans-serif; color: #1a1a1a;">
    <p>Olá, {{ $user->name }}.</p>

    <p>Recebemos uma solicitação para trocar o e-mail da sua conta no PegaTicket para <strong>{{ $newEmail }}</strong>.</p>

    <p>
        <a href="{{ $confirmUrl }}" style="display:inline-block;padding:12px 20px;background:#2563eb;color:#fff;text-decoration:none;border-radius:6px;">
            Confirmar novo e-mail
        </a>
    </p>

    <p>Ou copie e cole este link no navegador:</p>
    <p>{{ $confirmUrl }}</p>

    <p>Este link expira em 24 horas.</p>

    <p>Se você não solicitou essa troca, pode ignorar este e-mail — seu e-mail atual continua sendo usado normalmente.</p>
</body>
</html>
