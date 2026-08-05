<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
</head>
<body style="font-family: Arial, sans-serif; color: #1a1a1a;">
    <p>Olá<?php echo e($finalCustomer->name ? ', ' . $finalCustomer->name : ''); ?>.</p>

    <p>Faz um tempo que você não compra na <strong><?php echo e($tenant->name); ?></strong> — sentimos sua falta!</p>

    <p>Dá uma olhada nos próximos eventos e garanta seu ingresso antes que esgote.</p>

    <p>
        <a href="<?php echo e($storefrontUrl); ?>" style="display:inline-block;padding:12px 20px;background:#2563eb;color:#fff;text-decoration:none;border-radius:6px;">
            Ver eventos da <?php echo e($tenant->name); ?>

        </a>
    </p>

    <p>Se preferir, copie e cole este link no navegador:</p>
    <p><?php echo e($storefrontUrl); ?></p>
</body>
</html>
<?php /**PATH /home/mtsdrf/workspace/pegaticket-saas/api/resources/views/emails/recompra-nudge.blade.php ENDPATH**/ ?>