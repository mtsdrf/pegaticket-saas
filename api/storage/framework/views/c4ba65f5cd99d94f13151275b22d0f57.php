<?php $__env->startSection('preheader', 'Você recebeu um convite para acessar uma empresa no PegaTicket.'); ?>
<?php $__env->startSection('headline', 'Você recebeu um convite'); ?>
<?php $__env->startSection('subheadline', 'Confirme seu acesso para começar a operar junto com o time na plataforma.'); ?>

<?php $__env->startSection('content'); ?>
    <p style="margin:0 0 16px 0;">Olá, <?php echo e($invite->name); ?>.</p>
    <p style="margin:0 0 16px 0;">Você foi convidado(a) para fazer parte da empresa <strong><?php echo e($invite->tenant->name); ?></strong> no PegaTicket.</p>

    <?php echo $__env->make('emails.partials.button', ['url' => $inviteUrl, 'label' => 'Aceitar convite'], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
    <?php echo $__env->make('emails.partials.link-box', ['url' => $inviteUrl], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>

    <div style="margin-top:20px;padding:16px 18px;border-radius:16px;background-color:#f2f8f8;border:1px solid #d9e8e6;">
        <div style="font-size:14px;line-height:1.6;color:#143b33;">Este convite expira em <strong><?php echo e($invite->expires_at->format('d/m/Y H:i')); ?></strong>.</div>
    </div>

    <p style="margin:20px 0 0 0;color:#5d7470;">Se você não esperava este convite, pode ignorar este e-mail.</p>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('emails.layouts.base', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /home/mtsdrf/workspace/pegaticket-saas/api/resources/views/emails/tenant-user-invite.blade.php ENDPATH**/ ?>