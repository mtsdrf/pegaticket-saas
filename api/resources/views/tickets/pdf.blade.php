<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>Ingressos {{ $sale->codigo }}</title>
    <style>
        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 11px;
            color: #143b33;
            margin: 0;
            background: #eef4f7;
        }

        .page-shell {
            padding: 24px 26px 16px;
        }

        .brand-header {
            background: #115441;
            border-radius: 24px 24px 0 0;
            padding: 24px 28px 18px;
        }

        .brand-table {
            width: auto;
            margin: 0 auto;
            border-collapse: collapse;
        }

        .brand-table td {
            border: 0;
            padding: 0;
            vertical-align: middle;
        }

        .brand-logo {
            width: 104px;
            height: auto;
            display: block;
        }

        .brand-name {
            font-size: 32px;
            line-height: 1.05;
            font-weight: 700;
            color: #FFFFFF;
            padding-left: 14px;
        }

        .document-card {
            border: 1px solid #d5e4ea;
            border-top: 0;
            border-radius: 0 0 24px 24px;
            padding: 24px;
            background: #FFFFFF;
        }

        .document-title {
            font-size: 20px;
            font-weight: 700;
            color: #113d34;
            margin: 0 0 8px;
        }

        .document-subtitle {
            font-size: 12px;
            color: #5d7470;
            margin: 0 0 18px;
        }

        .summary-box {
            background: #f6fbfa;
            border: 1px solid #d5e4ea;
            border-radius: 14px;
            padding: 14px 16px;
            margin-bottom: 18px;
        }

        .summary-label {
            font-size: 10px;
            color: #6f8581;
            text-transform: uppercase;
            letter-spacing: 0.4px;
            margin-bottom: 4px;
        }

        .summary-value {
            font-size: 12px;
            color: #143b33;
        }

        .ticket-card {
            border: 1px solid #d5e4ea;
            border-radius: 16px;
            padding: 18px;
            margin-bottom: 14px;
            page-break-inside: avoid;
            background: #fcfffe;
        }

        .ticket-header {
            border-bottom: 1px solid #dfe9ed;
            padding-bottom: 10px;
            margin-bottom: 12px;
        }

        .event-name {
            font-size: 16px;
            font-weight: 700;
            color: #113d34;
            margin: 0 0 4px;
        }

        .event-meta {
            font-size: 11px;
            color: #5d7470;
            margin: 0;
        }

        .grid {
            width: 100%;
            border-collapse: collapse;
            margin-top: 8px;
        }

        .grid td {
            width: 50%;
            border: 0;
            padding: 0 10px 10px 0;
            vertical-align: top;
        }

        .ticket-layout {
            width: 100%;
            border-collapse: collapse;
            margin-top: 6px;
        }

        .ticket-layout td {
            border: 0;
            vertical-align: top;
        }

        .ticket-info-cell {
            width: 68%;
            padding-right: 14px;
        }

        .ticket-qr-cell {
            width: 32%;
        }

        .field-label {
            font-size: 9px;
            text-transform: uppercase;
            letter-spacing: 0.35px;
            color: #6f8581;
            margin-bottom: 3px;
        }

        .field-value {
            font-size: 12px;
            color: #143b33;
        }

        .code-box {
            margin-top: 8px;
            padding: 12px 14px;
            border-radius: 12px;
            background: #115441;
            color: #FFFFFF;
        }

        .code-label {
            font-size: 9px;
            text-transform: uppercase;
            letter-spacing: 0.45px;
            color: #a8c6bf;
            margin-bottom: 6px;
        }

        .code-value {
            font-size: 22px;
            font-weight: 700;
            letter-spacing: 1.2px;
            margin-bottom: 8px;
        }

        .qr-token {
            font-size: 10px;
            word-break: break-all;
            color: #dff7ef;
        }

        .qr-panel {
            background: #ffffff;
            border: 1px solid #d5e4ea;
            border-radius: 14px;
            padding: 12px;
            text-align: center;
        }

        .qr-panel-label {
            font-size: 9px;
            text-transform: uppercase;
            letter-spacing: 0.45px;
            color: #6f8581;
            margin-bottom: 8px;
        }

        .qr-image {
            width: 132px;
            height: 132px;
            display: block;
            margin: 0 auto 8px;
            border-radius: 12px;
            background: #ffffff;
        }

        .qr-caption {
            font-size: 10px;
            line-height: 1.5;
            color: #5d7470;
        }

        .validation-note {
            margin-top: 8px;
            font-size: 10px;
            color: #5d7470;
        }

        .footer-note {
            margin-top: 18px;
            font-size: 9px;
            color: #78908c;
            border-top: 1px solid #dfe9ed;
            padding-top: 8px;
        }
    </style>
</head>
<body>
    <div class="page-shell">
        <div class="brand-header">
            <table class="brand-table" role="presentation">
                <tr>
                    <td>
                        @if ($logoDataUri)
                            <img src="{{ $logoDataUri }}" alt="PegaTicket" class="brand-logo">
                        @endif
                    </td>
                    <td class="brand-name">PegaTicket</td>
                </tr>
            </table>
        </div>

        <div class="document-card">
            <h1 class="document-title">Ingressos da venda #{{ $sale->codigo }}</h1>
            <p class="document-subtitle">
                Documento preparado para leitura do cliente e validação na portaria.
            </p>

            <div class="summary-box">
                    <div class="summary-label">Compra</div>
                    <div class="summary-value">
                        Cliente: {{ $sale->finalCustomer?->name ?? 'Comprador' }}<br>
                        Empresa: {{ $tenantName ?? 'PegaTicket' }}<br>
                        Gerado em: {{ $generatedAt->format('d/m/Y H:i') }}<br>
                        Link da compra: {{ $trackingUrl }}
                    </div>
                </div>

            @foreach ($tickets as $ticket)
                <div class="ticket-card">
                    <div class="ticket-header">
                        <p class="event-name">{{ $ticket->ticketType?->event?->name ?? 'Evento' }}</p>
                        <p class="event-meta">
                            {{ $ticket->ticketType?->name ?? 'Ingresso' }}
                            @if ($ticket->ticketType?->session?->name)
                                · {{ $ticket->ticketType->session->name }}
                            @endif
                        </p>
                    </div>

                    <table class="grid" role="presentation">
                        <tr>
                            <td>
                                <div class="field-label">Participante</div>
                                <div class="field-value">{{ $ticket->attendee_name ?: ($sale->finalCustomer?->name ?? 'Não informado') }}</div>
                            </td>
                            <td>
                                <div class="field-label">Documento</div>
                                <div class="field-value">{{ $ticket->attendee_document ?: 'Não informado' }}</div>
                            </td>
                        </tr>
                        <tr>
                            <td>
                                <div class="field-label">Assento</div>
                                <div class="field-value">
                                    @if ($ticket->seat?->label)
                                        {{ $ticket->seat->label }}{{ $ticket->seat->sector_name ? ' — ' . $ticket->seat->sector_name : '' }}
                                    @else
                                        Não se aplica
                                    @endif
                                </div>
                            </td>
                            <td>
                                <div class="field-label">Status</div>
                                <div class="field-value">{{ ucfirst((string) $ticket->status) }}</div>
                            </td>
                        </tr>
                    </table>

                    @if (! empty($ticket->pdf_qr_data_uri))
                        <table class="ticket-layout" role="presentation">
                            <tr>
                                <td class="ticket-info-cell">
                                    <div class="code-box">
                                        <div class="code-label">Código do ingresso</div>
                                        <div class="code-value">{{ $ticket->code }}</div>
                                        <div class="code-label">Token do QR Code</div>
                                        <div class="qr-token">{{ $ticket->qr_token }}</div>
                                    </div>

                                    <div class="validation-note">
                                        Na validação, a equipe pode localizar este ingresso pelo código acima ou ler o QR Code deste cartão.
                                    </div>
                                </td>
                                <td class="ticket-qr-cell">
                                    <div class="qr-panel">
                                        <div class="qr-panel-label">QR Code do ingresso</div>
                                        <img src="{{ $ticket->pdf_qr_data_uri }}" alt="QR Code do ingresso {{ $ticket->code }}" class="qr-image">
                                        <div class="qr-caption">
                                            Apresente este QR na entrada para uma leitura mais rápida.
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        </table>
                    @else
                        <div class="code-box">
                            <div class="code-label">Código do ingresso</div>
                            <div class="code-value">{{ $ticket->code }}</div>
                            <div class="code-label">Token do QR Code</div>
                            <div class="qr-token">{{ $ticket->qr_token }}</div>
                        </div>

                        <div class="validation-note">
                            Na validação, a equipe pode localizar este ingresso pelo código acima ou pelo token do QR Code.
                        </div>
                    @endif
                </div>
            @endforeach

            <div class="footer-note">
                PegaTicket — apresentação clara para o cliente e conferência objetiva para a equipe de acesso.
            </div>
        </div>
    </div>
</body>
</html>
