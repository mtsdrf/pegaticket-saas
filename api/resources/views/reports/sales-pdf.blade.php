<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Relatório de Pedidos</title>
    @include('reports.partials.pdf-styles')
</head>
<body>
    @include('reports.partials.pdf-header', ['pdfTitle' => 'Relatório de Pedidos', 'tenantName' => $tenantName ?? null, 'generatedAt' => $generatedAt])

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
            @forelse ($sales as $order)
                <tr>
                    <td>{{ $order->client->name ?? '-' }}</td>
                    <td>{{ $order->created_at?->format('d/m/Y') }}</td>
                    <td class="text-right">{{ number_format((float) $order->total_amount, 2, ',', '.') }}</td>
                    <td>{{ $order->is_paid ? 'Sim' : 'Não' }}</td>
                    <td>{{ $order->is_delivered ? 'Sim' : 'Não' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="5">Nenhum pedido encontrado para os filtros informados.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <p class="totals">Total de pedidos: {{ $sales->count() }}</p>

    @include('reports.partials.pdf-footer')
</body>
</html>
