<?php $__env->startSection('preheader', 'Novos eventos disponíveis para você comprar novamente.'); ?>
<?php $__env->startSection('headline', 'Sentimos sua falta por aqui'); ?>
<?php $__env->startSection('subheadline', 'Seu histórico mostra que você já curtiu experiências com esta empresa. Aproveite para ver o que está disponível agora.'); ?>

<?php $__env->startSection('content'); ?>
    <p style="margin:0 0 16px 0;">Olá<?php echo e($finalCustomer->name ? ', ' . $finalCustomer->name : ''); ?>.</p>
    <p style="margin:0 0 16px 0;">Faz um tempo que você não compra na <strong><?php echo e($tenant->name); ?></strong> e queremos facilitar o seu retorno.</p>
    <p style="margin:0 0 16px 0;">Dê uma olhada nos próximos eventos e garanta seu ingresso antes que as vagas acabem.</p>

    <?php echo $__env->make('emails.partials.button', ['url' => $storefrontUrl, 'label' => 'Ver eventos da ' . $tenant->name], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
    <?php echo $__env->make('emails.partials.link-box', ['url' => $storefrontUrl], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('emails.layouts.base', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /home/mtsdrf/workspace/pegaticket-saas/api/resources/views/emails/recompra-nudge.blade.php ENDPATH**/ ?>