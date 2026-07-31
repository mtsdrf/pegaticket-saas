{{--
    Cabeçalho compartilhado por todo PDF Maskats. Espera:
    - $pdfTitle: string, título do documento (ex: "Catálogo de Produtos").
    - $tenantName: string|null, nome do tenant atual (tenant()->name).
    - $generatedAt: Carbon, data/hora de geração.
--}}
<header class="mk-pdf-header">
    <h1>Maskats</h1>
    <p class="mk-pdf-subtitle">{{ $pdfTitle }}{{ !empty($tenantName) ? ' — ' . $tenantName : '' }}</p>
    <p class="mk-pdf-meta">Gerado em: {{ $generatedAt->format('d/m/Y H:i') }}</p>
</header>
