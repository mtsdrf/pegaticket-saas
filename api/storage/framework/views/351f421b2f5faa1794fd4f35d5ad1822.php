<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Catálogo de Produtos</title>
    <?php echo $__env->make('reports.partials.pdf-styles', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
    <style>
        .catalog-table { width: 100%; border-collapse: separate; border-spacing: 10px; margin-top: 4px; }
        .catalog-cell {
            width: 33.33%;
            vertical-align: top;
            border: 1px solid #E5E7EB;
            border-radius: 4px;
            padding: 8px;
            page-break-inside: avoid;
        }
        .catalog-cell.empty { border: none; padding: 0; }
        .catalog-image-frame {
            width: 100%;
            height: 120px;
            background-color: #F8FAFC;
            border: 1px solid #E5E7EB;
            border-radius: 3px;
            margin-bottom: 6px;
            text-align: center;
        }
        .catalog-image-frame td { vertical-align: middle; text-align: center; }
        .catalog-image-frame img { max-width: 100%; max-height: 112px; }
        .catalog-cell h4 { margin: 0 0 3px; font-size: 12px; color: #1A1A1A; }
        .catalog-cell .category { margin: 0 0 4px; font-size: 9px; color: #6B7280; }
        .catalog-cell .price { margin: 0; font-size: 13px; font-weight: bold; color: #0F3D5E; }
        .catalog-cell .sku { margin: 2px 0 0; font-size: 9px; color: #6B7280; }
    </style>
</head>
<body>
    <?php echo $__env->make('reports.partials.pdf-header', ['pdfTitle' => 'Catálogo de Produtos', 'tenantName' => $tenantName ?? null, 'generatedAt' => $generatedAt], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>

    <?php if($products->isEmpty()): ?>
        <p>Nenhum produto encontrado para os filtros informados.</p>
    <?php else: ?>
        <table class="catalog-table">
            <?php $__currentLoopData = $products->chunk(3); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $row): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <tr>
                    <?php $__currentLoopData = $row; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $product): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
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
                        <td class="catalog-cell">
                            <table class="catalog-image-frame">
                                <tr><td>
                                    <?php if($imageSrc): ?>
                                        <img src="<?php echo e($imageSrc); ?>" alt="<?php echo e($product->name); ?>">
                                    <?php endif; ?>
                                </td></tr>
                            </table>

                            <h4><?php echo e($product->name); ?></h4>

                            <?php if($categoryLabel): ?>
                                <p class="category"><?php echo e($categoryLabel); ?></p>
                            <?php endif; ?>

                            <p class="price">R$ <?php echo e(number_format((float) $product->price, 2, ',', '.')); ?></p>

                            <?php if($product->sku): ?>
                                <p class="sku">SKU: <?php echo e($product->sku); ?></p>
                            <?php endif; ?>

                            <?php if (! ($product->is_available)): ?>
                                <p><span class="mk-badge mk-badge-muted">Indisponível</span></p>
                            <?php endif; ?>
                        </td>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>

                    <?php for($i = $row->count(); $i < 3; $i++): ?>
                        <td class="catalog-cell empty"></td>
                    <?php endfor; ?>
                </tr>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </table>
    <?php endif; ?>

    <p class="totals">Total de produtos: <?php echo e($products->count()); ?></p>

    <?php echo $__env->make('reports.partials.pdf-footer', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
</body>
</html>
<?php /**PATH /home/mtsdrf/workspace/pegaticket-saas/api/resources/views/products/pdf.blade.php ENDPATH**/ ?>