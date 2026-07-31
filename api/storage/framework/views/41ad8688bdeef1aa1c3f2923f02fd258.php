
<header class="mk-pdf-header">
    <h1>Maskats</h1>
    <p class="mk-pdf-subtitle"><?php echo e($pdfTitle); ?><?php echo e(!empty($tenantName) ? ' — ' . $tenantName : ''); ?></p>
    <p class="mk-pdf-meta">Gerado em: <?php echo e($generatedAt->format('d/m/Y H:i')); ?></p>
</header>
<?php /**PATH /home/mtsdrf/workspace/pegaticket-saas/api/resources/views/reports/partials/pdf-header.blade.php ENDPATH**/ ?>