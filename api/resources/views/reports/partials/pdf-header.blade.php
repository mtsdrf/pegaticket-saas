{{--
    Cabeçalho compartilhado por todo PDF PegaTicket. Espera:
    - $pdfTitle: string, título do documento (ex: "Catálogo de Produtos").
    - $tenantName: string|null, nome do tenant atual (tenant()->name).
    - $generatedAt: Carbon, data/hora de geração.
--}}
<header class="pt-pdf-header">
    <h1>PegaTicket</h1>
    <p class="pt-pdf-subtitle">{{ $pdfTitle }}{{ !empty($tenantName) ? ' — ' . $tenantName : '' }}</p>
    <p class="pt-pdf-meta">Gerado em: {{ $generatedAt->format('d/m/Y H:i') }}</p>
</header>
