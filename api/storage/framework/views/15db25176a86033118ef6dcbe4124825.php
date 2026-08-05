<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
</head>
<body style="font-family: Arial, sans-serif; color: #1a1a1a;">
    <p>Olá.</p>

    <p>Use o código abaixo para entrar no seu painel de compras PegaTicket:</p>

    <p style="font-size:32px;font-weight:bold;letter-spacing:8px;background:#f4f4f5;padding:16px 20px;border-radius:8px;display:inline-block;">
        <?php echo e($code); ?>

    </p>

    <p>Este código é válido por <?php echo e($expiresInMinutes); ?> minutos.</p>

    <p>Se você não solicitou este código, pode ignorar este e-mail.</p>
</body>
</html>
<?php /**PATH /home/mtsdrf/workspace/pegaticket-saas/api/resources/views/emails/portal-otp.blade.php ENDPATH**/ ?>