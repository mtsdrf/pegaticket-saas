<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
</head>
<body style="font-family: Arial, sans-serif; color: #1a1a1a;">
    <p>Olá.</p>

    <?php if($mode === 'resent'): ?>
        <p>Reenviamos os dados do seu ingresso da venda <strong>#<?php echo e($sale->codigo); ?></strong>.</p>
    <?php else: ?>
        <p>Seu pagamento foi confirmado e seus ingressos da venda <strong>#<?php echo e($sale->codigo); ?></strong> já estão prontos.</p>
    <?php endif; ?>

    <p>Resumo dos ingressos:</p>

    <ul>
        <?php $__currentLoopData = $tickets; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $ticket): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <li style="margin-bottom: 10px;">
                <strong><?php echo e($ticket->ticketType?->event?->name ?? 'Evento'); ?></strong><br>
                <?php if($ticket->ticketType?->session?->name): ?>
                    Sessão: <?php echo e($ticket->ticketType->session->name); ?><br>
                <?php endif; ?>
                Tipo: <?php echo e($ticket->ticketType?->name ?? 'Ingresso'); ?><br>
                Código: <?php echo e($ticket->code); ?><br>
                <?php if($ticket->attendee_name): ?>
                    Participante: <?php echo e($ticket->attendee_name); ?><br>
                <?php endif; ?>
                <?php if($ticket->seat?->label): ?>
                    Assento: <?php echo e($ticket->seat->label); ?><?php if($ticket->seat->sector_name): ?> - <?php echo e($ticket->seat->sector_name); ?><?php endif; ?><br>
                <?php endif; ?>
            </li>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
    </ul>

    <p>
        <a href="<?php echo e($trackingUrl); ?>" style="display:inline-block;padding:12px 20px;background:#2563eb;color:#fff;text-decoration:none;border-radius:6px;">
            Acompanhar venda
        </a>
    </p>

    <p>Se preferir, copie e cole este link no navegador:</p>
    <p><?php echo e($trackingUrl); ?></p>
</body>
</html>
<?php /**PATH /home/mtsdrf/workspace/pegaticket-saas/api/resources/views/emails/ticket-delivery.blade.php ENDPATH**/ ?>