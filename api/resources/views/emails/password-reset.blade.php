<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
</head>
<body style="font-family: Arial, sans-serif; color: #1a1a1a;">
    <p>Olá, {{ $user->name }}.</p>

    <p>Recebemos uma solicitação para redefinir a senha da sua conta no PegaTicket.</p>

    <p>
        <a href="{{ $resetUrl }}" style="display:inline-block;padding:12px 20px;background:#2563eb;color:#fff;text-decoration:none;border-radius:6px;">
            Redefinir senha
        </a>
    </p>

    <p>Ou copie e cole este link no navegador:</p>
    <p>{{ $resetUrl }}</p>

    <p>Este link expira em 1 hora.</p>

    <p>Se você não solicitou essa redefinição, pode ignorar este e-mail — sua senha atual continua sendo usada normalmente.</p>
</body>
</html>
