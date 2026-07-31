<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Catálogo de Produtos</title>
    @include('reports.partials.pdf-styles')
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
    @include('reports.partials.pdf-header', ['pdfTitle' => 'Catálogo de Produtos', 'tenantName' => $tenantName ?? null, 'generatedAt' => $generatedAt])

    @if ($products->isEmpty())
        <p>Nenhum produto encontrado para os filtros informados.</p>
    @else
        <table class="catalog-table">
            @foreach ($products->chunk(3) as $row)
                <tr>
                    @foreach ($row as $product)
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
                        <td class="catalog-cell">
                            <table class="catalog-image-frame">
                                <tr><td>
                                    @if ($imageSrc)
                                        <img src="{{ $imageSrc }}" alt="{{ $product->name }}">
                                    @endif
                                </td></tr>
                            </table>

                            <h4>{{ $product->name }}</h4>

                            @if ($categoryLabel)
                                <p class="category">{{ $categoryLabel }}</p>
                            @endif

                            <p class="price">R$ {{ number_format((float) $product->price, 2, ',', '.') }}</p>

                            @if ($product->sku)
                                <p class="sku">SKU: {{ $product->sku }}</p>
                            @endif

                            @unless ($product->is_available)
                                <p><span class="mk-badge mk-badge-muted">Indisponível</span></p>
                            @endunless
                        </td>
                    @endforeach

                    @for ($i = $row->count(); $i < 3; $i++)
                        <td class="catalog-cell empty"></td>
                    @endfor
                </tr>
            @endforeach
        </table>
    @endif

    <p class="totals">Total de produtos: {{ $products->count() }}</p>

    @include('reports.partials.pdf-footer')
</body>
</html>
