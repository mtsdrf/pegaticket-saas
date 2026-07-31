<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Diretório de Clientes</title>
    @include('reports.partials.pdf-styles')
    <style>
        .categories-cell { font-size: 9px; color: #6B7280; }
    </style>
</head>
<body>
    @include('reports.partials.pdf-header', ['pdfTitle' => 'Diretório de Clientes', 'tenantName' => $tenantName ?? null, 'generatedAt' => $generatedAt])

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
            @forelse ($clients as $client)
                @php
                    $endereco = $client->endereco;
                    $enderecoLine = trim(
                        ($endereco?->logradouro ?? '')
                        . ($endereco?->numero ? ', ' . $endereco->numero : '')
                        . ($endereco?->complemento ? ' - ' . $endereco->complemento : '')
                    );
                    $categoriesLabel = $client->categories->pluck('name')->implode(', ');
                @endphp
                <tr>
                    <td>{{ $client->name }}</td>
                    <td>{{ $client->phone_primary ?: '-' }}</td>
                    <td>{{ $client->phone_secondary ?: '-' }}</td>
                    <td>{{ $enderecoLine ?: '-' }}</td>
                    <td>{{ $endereco?->bairro?->name ?? '-' }}</td>
                    <td>{{ $endereco?->cidade?->name ?? '-' }}{{ $endereco?->estado?->uf ? '/' . $endereco->estado->uf : '' }}</td>
                    <td>{{ $endereco?->cep ?: '-' }}</td>
                    <td class="categories-cell">{{ $categoriesLabel ?: '-' }}</td>
                    <td>
                        <span class="pt-badge {{ $client->is_active ? 'pt-badge-success' : 'pt-badge-muted' }}">
                            {{ $client->is_active ? 'Ativo' : 'Inativo' }}
                        </span>
                        @if ($client->is_trusted)
                            <span class="pt-badge pt-badge-success">Confiável</span>
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="9">Nenhum cliente encontrado para os filtros informados.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <p class="totals">Total de clientes: {{ $clients->count() }}</p>

    @include('reports.partials.pdf-footer')
</body>
</html>
