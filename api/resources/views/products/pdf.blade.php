<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Catálogo de Produtos</title>
    @include('reports.partials.pdf-styles')
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
    @include('reports.partials.pdf-header', ['pdfTitle' => 'Catálogo de Produtos', 'tenantName' => $tenantName ?? null, 'generatedAt' => $generatedAt])

    @if ($products->isEmpty())
        <p>Nenhum produto encontrado para os filtros informados.</p>
    @else
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
                @foreach ($products as $product)
                    @php
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
                    @endphp
                    <tr>
                        <td class="catalog-thumb">
                            @if ($imageSrc)
                                <img src="{{ $imageSrc }}" alt="{{ $product->name }}">
                            @else
                                <span class="catalog-muted">Sem imagem</span>
                            @endif
                        </td>
                        <td>{{ $product->name }}</td>
                        <td>{{ $categoryLabel ?: '-' }}</td>
                        <td class="text-right">R$ {{ number_format((float) $product->price, 2, ',', '.') }}</td>
                        <td>{{ $product->sku ?: '-' }}</td>
                        <td>
                            <span class="pt-badge {{ $product->is_available ? 'pt-badge-success' : 'pt-badge-muted' }}">
                                {{ $product->is_available ? 'Disponível' : 'Indisponível' }}
                            </span>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <p class="totals">Total de produtos: {{ $products->count() }}</p>

    @include('reports.partials.pdf-footer')
</body>
</html>
