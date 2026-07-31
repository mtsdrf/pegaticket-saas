<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Relatório de Clientes</title>
    <?php echo $__env->make('reports.partials.pdf-styles', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
    <style>
        .client-block { margin-bottom: 14px; }
        .client-block h3 { margin: 0 0 4px; font-size: 13px; color: #1A1A1A; }
        .client-block p.client-meta { margin: 0 0 4px; font-size: 10px; color: #6B7280; }
    </style>
</head>
<body>
    <?php echo $__env->make('reports.partials.pdf-header', ['pdfTitle' => 'Relatório de Clientes', 'tenantName' => $tenantName ?? null, 'generatedAt' => $generatedAt], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>

    <?php $__empty_1 = true; $__currentLoopData = $clients; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $client): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
        <div class="client-block">
            <h3><?php echo e($client->name); ?></h3>
            <p class="client-meta">
                <?php echo e($client->endereco?->cidade?->name); ?> / <?php echo e($client->endereco?->bairro?->name); ?>

                — <?php echo e($client->phone_primary ?? 'sem telefone'); ?>

            </p>

            <table>
                <thead>
                    <tr>
                        <th>Data</th>
                        <th class="text-right">Valor total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $__empty_2 = true; $__currentLoopData = $client->orders; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $order): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_2 = false; ?>
                        <tr>
                            <td><?php echo e($order->created_at?->format('d/m/Y')); ?></td>
                            <td class="text-right"><?php echo e(number_format((float) $order->total_amount, 2, ',', '.')); ?></td>
                        </tr>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_2): ?>
                        <tr>
                            <td colspan="2">Sem pedidos pagos e entregues no período.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
        <p>Nenhum cliente encontrado para os filtros informados.</p>
    <?php endif; ?>

    <?php echo $__env->make('reports.partials.pdf-footer', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
</body>
</html>
<?php /**PATH /home/mtsdrf/workspace/pegaticket-saas/api/resources/views/reports/clients-pdf.blade.php ENDPATH**/ ?>