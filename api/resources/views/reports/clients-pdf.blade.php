<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Relatório de Clientes</title>
    @include('reports.partials.pdf-styles')
    <style>
        .client-block { margin-bottom: 14px; }
        .client-block h3 { margin: 0 0 4px; font-size: 13px; color: #1A1A1A; }
        .client-block p.client-meta { margin: 0 0 4px; font-size: 10px; color: #6B7280; }
    </style>
</head>
<body>
    @include('reports.partials.pdf-header', ['pdfTitle' => 'Relatório de Clientes', 'tenantName' => $tenantName ?? null, 'generatedAt' => $generatedAt])

    @forelse ($clients as $client)
        <div class="client-block">
            <h3>{{ $client->name }}</h3>
            <p class="client-meta">
                {{ $client->endereco?->cidade?->name }} / {{ $client->endereco?->bairro?->name }}
                — {{ $client->phone_primary ?? 'sem telefone' }}
            </p>

            <table>
                <thead>
                    <tr>
                        <th>Data</th>
                        <th class="text-right">Valor total</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($client->orders as $order)
                        <tr>
                            <td>{{ $order->created_at?->format('d/m/Y') }}</td>
                            <td class="text-right">{{ number_format((float) $order->total_amount, 2, ',', '.') }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="2">Sem pedidos pagos e entregues no período.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @empty
        <p>Nenhum cliente encontrado para os filtros informados.</p>
    @endforelse

    @include('reports.partials.pdf-footer')
</body>
</html>
