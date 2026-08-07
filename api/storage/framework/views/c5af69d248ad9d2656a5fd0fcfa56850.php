<?php $__env->startSection('preheader', 'Seus ingressos e dados da venda já estão disponíveis.'); ?>
<?php $__env->startSection('headline', 'Seu ingresso está pronto'); ?>
<?php $__env->startSection('subheadline', 'Centralizamos abaixo os dados principais da sua venda para facilitar o acompanhamento e o acesso ao evento.'); ?>

<?php $__env->startSection('content'); ?>
    <p style="margin:0 0 16px 0;">Olá.</p>

    <?php if($mode === 'resent'): ?>
        <p style="margin:0 0 16px 0;">Reenviamos os dados do seu ingresso da venda <strong>#<?php echo e($sale->codigo); ?></strong>.</p>
    <?php elseif($mode === 'reminder'): ?>
        <p style="margin:0 0 16px 0;">Seu evento está chegando. Aqui está o lembrete dos ingressos da venda <strong>#<?php echo e($sale->codigo); ?></strong>.</p>
    <?php elseif($mode === 'transferred'): ?>
        <p style="margin:0 0 16px 0;">A titularidade de um ingresso da venda <strong>#<?php echo e($sale->codigo); ?></strong> foi transferida. O novo QR Code já está disponível e o anterior não é mais válido.</p>
    <?php else: ?>
        <p style="margin:0 0 16px 0;">Seu pagamento foi confirmado e os ingressos da venda <strong>#<?php echo e($sale->codigo); ?></strong> já estão prontos.</p>
    <?php endif; ?>

    <div style="margin:24px 0 18px 0;padding:18px;border-radius:18px;background-color:#f7fbfc;border:1px solid #d9e8e6;">
        <div style="font-size:14px;font-weight:700;color:#113d34;margin-bottom:14px;">Resumo dos ingressos</div>
        <?php $__currentLoopData = $tickets; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $ticket): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <div style="<?php echo e($loop->last ? '' : 'margin-bottom:14px;padding-bottom:14px;border-bottom:1px solid #d9e8e6;'); ?>">
                <div style="font-size:15px;font-weight:700;color:#113d34;margin-bottom:4px;"><?php echo e($ticket->ticketType?->event?->name ?? 'Evento'); ?></div>
                <?php if($ticket->ticketType?->session?->name): ?>
                    <div style="font-size:14px;color:#143b33;">Sessão: <?php echo e($ticket->ticketType->session->name); ?></div>
                <?php endif; ?>
                <div style="font-size:14px;color:#143b33;">Tipo: <?php echo e($ticket->ticketType?->name ?? 'Ingresso'); ?></div>
                <div style="font-size:14px;color:#143b33;">Código: <?php echo e($ticket->code); ?></div>
                <?php if($ticket->attendee_name): ?>
                    <div style="font-size:14px;color:#143b33;">Participante: <?php echo e($ticket->attendee_name); ?></div>
                <?php endif; ?>
                <?php if($ticket->seat?->label): ?>
                    <div style="font-size:14px;color:#143b33;">Assento: <?php echo e($ticket->seat->label); ?><?php if($ticket->seat->sector_name): ?> - <?php echo e($ticket->seat->sector_name); ?><?php endif; ?></div>
                <?php endif; ?>
            </div>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
    </div>

    <?php echo $__env->make('emails.partials.button', ['url' => $trackingUrl, 'label' => 'Acompanhar venda'], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
    <?php echo $__env->make('emails.partials.button', ['url' => $ticketPdfUrl, 'label' => 'Baixar PDF do ingresso'], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
    <?php echo $__env->make('emails.partials.link-box', ['url' => $trackingUrl], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
    <?php echo $__env->make('emails.partials.link-box', ['url' => $ticketPdfUrl, 'label' => 'Se preferir baixar o PDF diretamente, use este link:'], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('emails.layouts.base', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /home/mtsdrf/workspace/pegaticket-saas/api/resources/views/emails/ticket-delivery.blade.php ENDPATH**/ ?>