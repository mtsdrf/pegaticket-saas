<?php $__env->startSection('preheader', 'Seu código temporário de acesso ao portal de compras.'); ?>
<?php $__env->startSection('headline', 'Código de acesso ao portal'); ?>
<?php $__env->startSection('subheadline', 'Use este código temporário para entrar no seu painel de compras do PegaTicket.'); ?>

<?php $__env->startSection('content'); ?>
    <p style="margin:0 0 18px 0;">Olá.</p>

    <div style="display:inline-block;margin:4px 0 20px 0;padding:18px 22px;border-radius:18px;background-color:#f2f8f8;border:1px solid #d9e8e6;font-size:34px;line-height:1;font-weight:700;letter-spacing:10px;color:#08cfa7;">
        <?php echo e($code); ?>

    </div>

    <p style="margin:0 0 16px 0;">Este código é válido por <strong><?php echo e($expiresInMinutes); ?> minutos</strong>.</p>
    <p style="margin:0;color:#5d7470;">Se você não solicitou este código, pode ignorar este e-mail.</p>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('emails.layouts.base', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /home/mtsdrf/workspace/pegaticket-saas/api/resources/views/emails/portal-otp.blade.php ENDPATH**/ ?>