{{--
    CSS compartilhado por todo PDF gerado via DomPDF (reports/orders-pdf,
    products/pdf, clients/pdf). Cores fixas da marca
    PegaTicket — DomPDF não lê custom properties (--pt-*) do app, por isso os
    valores estão hardcoded aqui em vez de referenciar o design system web.
    'DejaVu Sans' é a única fonte embutida no DomPDF com suporte confiável a
    acentuação PT-BR (não usar Inter/Manrope, DomPDF não acessa fontes web).
--}}
<style>
    body { font-family: 'DejaVu Sans', sans-serif; font-size: 11px; color: #1A1A1A; }

    header.pt-pdf-header { margin-bottom: 16px; border-bottom: 2px solid #0F3D5E; padding-bottom: 10px; }
    header.pt-pdf-header h1 { margin: 0; font-size: 20px; color: #0F3D5E; }
    header.pt-pdf-header p.pt-pdf-subtitle { margin: 2px 0 0; font-size: 13px; color: #1A1A1A; }
    header.pt-pdf-header p.pt-pdf-meta { margin: 6px 0 0; font-size: 9px; color: #6B7280; }

    table { width: 100%; border-collapse: collapse; margin-top: 10px; }
    th, td { border: 1px solid #E5E7EB; padding: 5px 6px; text-align: left; }
    th { background-color: #0F3D5E; color: #FFFFFF; }
    tbody tr:nth-child(even) { background-color: #F8FAFC; }
    .text-right { text-align: right; }
    .totals { margin-top: 12px; font-weight: bold; color: #0F3D5E; }

    .pt-badge {
        display: inline-block;
        padding: 2px 6px;
        border-radius: 3px;
        font-size: 9px;
        border: 1px solid #E5E7EB;
    }
    .pt-badge-muted { background-color: #F8FAFC; color: #6B7280; }
    .pt-badge-success { background-color: #F8FAFC; color: #0F3D5E; border-color: #0F3D5E; }

    footer.pt-pdf-footer,
    .pt-pdf-footer {
        margin-top: 16px;
        font-size: 9px;
        color: #6B7280;
        border-top: 1px solid #E5E7EB;
        padding-top: 6px;
    }
</style>
