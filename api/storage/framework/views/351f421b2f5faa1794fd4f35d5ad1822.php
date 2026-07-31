<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Catálogo de Produtos</title>
    <?php echo $__env->make('reports.partials.pdf-styles', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
    <style>
        .catalog-thumb {
            width: 56px;
            height: 56px;
            border: 1px solid #E5E7EB;
            background: #F8FAFC;
            text-align: center;
            vertical-align: middle;
        }
        .catalog-thumb img {
            max-width: 52px;
            max-height: 52px;
        }
        .catalog-muted {
            color: #6B7280;
            font-size: 9px;
        }
    </style>
</head>
<body>
    <?php echo $__env->make('reports.partials.pdf-header', ['pdfTitle' => 'Catálogo de Produtos', 'tenantName' => $tenantName ?? null, 'generatedAt' => $generatedAt], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>

    <?php if($products->isEmpty()): ?>
        <p>Nenhum produto encontrado para os filtros informados.</p>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th style="width: 70px;">Imagem</th>
                    <th>Produto</th>
                    <th>Categoria</th>
                    <th class="text-right" style="width: 90px;">Preço</th>
                    <th style="width: 90px;">SKU</th>
                    <th style="width: 90px;">Status</th>
                </tr>
            </thead>
            <tbody>
                <?php $__currentLoopData = $products; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $product): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <?php
                        $imageSrc = null;

                        if ($product->image_data && $product->image_mime) {
                            $imageSrc = 'data:' . $product->image_mime . ';base64,' . base64_encode($product->image_data);
                        } elseif ($product->image_path && \Illuminate\Support\Facades\Storage::disk('public')->exists($product->image_path)) {
                            $imageSrc = 'data:' . ($product->image_mime ?? 'application/octet-stream') . ';base64,' . base64_encode(\Illuminate\Support\Facades\Storage::disk('public')->get($product->image_path));
                        } elseif (file_exists(public_path('images/produto-sem-foto.png'))) {
                            $imageSrc = 'data:image/png;base64,' . base64_encode(file_get_contents(public_path('images/produto-sem-foto.png')));
                        }

                        $categoryLabel = trim(
                            ($product->productType?->productCategory?->name ? $product->productType->productCategory->name . ' › ' : '')
                            . ($product->productType?->name ?? '')
                        );
                    ?>
                    <tr>
                        <td class="catalog-thumb">
                            <?php if($imageSrc): ?>
                                <img src="<?php echo e($imageSrc); ?>" alt="<?php echo e($product->name); ?>">
                            <?php else: ?>
                                <span class="catalog-muted">Sem imagem</span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo e($product->name); ?></td>
                        <td><?php echo e($categoryLabel ?: '-'); ?></td>
                        <td class="text-right">R$ <?php echo e(number_format((float) $product->price, 2, ',', '.')); ?></td>
                        <td><?php echo e($product->sku ?: '-'); ?></td>
                        <td>
                            <span class="pt-badge <?php echo e($product->is_available ? 'pt-badge-success' : 'pt-badge-muted'); ?>">
                                <?php echo e($product->is_available ? 'Disponível' : 'Indisponível'); ?>

                            </span>
                        </td>
                    </tr>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </tbody>
        </table>
    <?php endif; ?>

    <p class="totals">Total de produtos: <?php echo e($products->count()); ?></p>

    <?php echo $__env->make('reports.partials.pdf-footer', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
</body>
</html>
<?php /**PATH /home/mtsdrf/workspace/pegaticket-saas/api/resources/views/products/pdf.blade.php ENDPATH**/ ?>