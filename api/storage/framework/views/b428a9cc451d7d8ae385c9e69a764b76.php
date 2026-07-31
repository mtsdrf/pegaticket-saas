<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Diretório de Clientes</title>
    <?php echo $__env->make('reports.partials.pdf-styles', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
    <style>
        .categories-cell { font-size: 9px; color: #6B7280; }
    </style>
</head>
<body>
    <?php echo $__env->make('reports.partials.pdf-header', ['pdfTitle' => 'Diretório de Clientes', 'tenantName' => $tenantName ?? null, 'generatedAt' => $generatedAt], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>

    <table>
        <thead>
            <tr>
                <th>Nome</th>
                <th>Telefone principal</th>
                <th>Telefone secundário</th>
                <th>Endereço</th>
                <th>Bairro</th>
                <th>Cidade/UF</th>
                <th>CEP</th>
                <th>Categorias</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            <?php $__empty_1 = true; $__currentLoopData = $clients; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $client): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                <?php
                    $endereco = $client->endereco;
                    $enderecoLine = trim(
                        ($endereco?->logradouro ?? '')
                        . ($endereco?->numero ? ', ' . $endereco->numero : '')
                        . ($endereco?->complemento ? ' - ' . $endereco->complemento : '')
                    );
                    $categoriesLabel = $client->categories->pluck('name')->implode(', ');
                ?>
                <tr>
                    <td><?php echo e($client->name); ?></td>
                    <td><?php echo e($client->phone_primary ?: '-'); ?></td>
                    <td><?php echo e($client->phone_secondary ?: '-'); ?></td>
                    <td><?php echo e($enderecoLine ?: '-'); ?></td>
                    <td><?php echo e($endereco?->bairro?->name ?? '-'); ?></td>
                    <td><?php echo e($endereco?->cidade?->name ?? '-'); ?><?php echo e($endereco?->estado?->uf ? '/' . $endereco->estado->uf : ''); ?></td>
                    <td><?php echo e($endereco?->cep ?: '-'); ?></td>
                    <td class="categories-cell"><?php echo e($categoriesLabel ?: '-'); ?></td>
                    <td>
                        <span class="mk-badge <?php echo e($client->is_active ? 'mk-badge-success' : 'mk-badge-muted'); ?>">
                            <?php echo e($client->is_active ? 'Ativo' : 'Inativo'); ?>

                        </span>
                        <?php if($client->is_trusted): ?>
                            <span class="mk-badge mk-badge-success">Confiável</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                <tr>
                    <td colspan="9">Nenhum cliente encontrado para os filtros informados.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>

    <p class="totals">Total de clientes: <?php echo e($clients->count()); ?></p>

    <?php echo $__env->make('reports.partials.pdf-footer', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
</body>
</html>
<?php /**PATH /home/mtsdrf/workspace/pegaticket-saas/api/resources/views/clients/pdf.blade.php ENDPATH**/ ?>