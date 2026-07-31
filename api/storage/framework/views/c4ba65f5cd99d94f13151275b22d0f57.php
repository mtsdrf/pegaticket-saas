<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
</head>
<body style="font-family: Arial, sans-serif; color: #1a1a1a;">
    <p>Olá, <?php echo e($invite->name); ?>.</p>

    <p>Você foi convidado(a) para fazer parte da empresa <strong><?php echo e($invite->tenant->name); ?></strong> no Maskats.</p>

    <p>
        <a href="<?php echo e($inviteUrl); ?>" style="display:inline-block;padding:12px 20px;background:#2563eb;color:#fff;text-decoration:none;border-radius:6px;">
            Aceitar convite
        </a>
    </p>

    <p>Ou copie e cole este link no navegador:</p>
    <p><?php echo e($inviteUrl); ?></p>

    <p>Este convite expira em <?php echo e($invite->expires_at->format('d/m/Y H:i')); ?>.</p>

    <p>Se você não esperava este convite, pode ignorar este e-mail.</p>
</body>
</html>
<?php /**PATH /home/mtsdrf/workspace/pegaticket-saas/api/resources/views/emails/tenant-user-invite.blade.php ENDPATH**/ ?>