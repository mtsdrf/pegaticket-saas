<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Relatório de Pedidos</title>
    <?php echo $__env->make('reports.partials.pdf-styles', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
</head>
<body>
    <?php echo $__env->make('reports.partials.pdf-header', ['pdfTitle' => 'Relatório de Pedidos', 'tenantName' => $tenantName ?? null, 'generatedAt' => $generatedAt], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>

    <table>
        <thead>
            <tr>
                <th>Cliente</th>
                <th>Data</th>
                <th class="text-right">Valor total</th>
                <th>Pago</th>
                <th>Entregue</th>
            </tr>
        </thead>
        <tbody>
            <?php $__empty_1 = true; $__currentLoopData = $orders; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $order): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                <tr>
                    <td><?php echo e($order->client->name ?? '-'); ?></td>
                    <td><?php echo e($order->created_at?->format('d/m/Y')); ?></td>
                    <td class="text-right"><?php echo e(number_format((float) $order->total_amount, 2, ',', '.')); ?></td>
                    <td><?php echo e($order->is_paid ? 'Sim' : 'Não'); ?></td>
                    <td><?php echo e($order->is_delivered ? 'Sim' : 'Não'); ?></td>
                </tr>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                <tr>
                    <td colspan="5">Nenhum pedido encontrado para os filtros informados.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>

    <p class="totals">Total de pedidos: <?php echo e($orders->count()); ?></p>

    <?php echo $__env->make('reports.partials.pdf-footer', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
</body>
</html>
<?php /**PATH /home/mtsdrf/workspace/pegaticket-saas/api/resources/views/reports/orders-pdf.blade.php ENDPATH**/ ?>