<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
</head>
<body style="font-family: Arial, sans-serif; color: #1a1a1a;">
    <p>Olá.</p>

    @if ($mode === 'resent')
        <p>Reenviamos os dados do seu ingresso da venda <strong>#{{ $sale->codigo }}</strong>.</p>
    @else
        <p>Seu pagamento foi confirmado e seus ingressos da venda <strong>#{{ $sale->codigo }}</strong> já estão prontos.</p>
    @endif

    <p>Resumo dos ingressos:</p>

    <ul>
        @foreach ($tickets as $ticket)
            <li style="margin-bottom: 10px;">
                <strong>{{ $ticket->ticketType?->event?->name ?? 'Evento' }}</strong><br>
                @if ($ticket->ticketType?->session?->name)
                    Sessão: {{ $ticket->ticketType->session->name }}<br>
                @endif
                Tipo: {{ $ticket->ticketType?->name ?? 'Ingresso' }}<br>
                Código: {{ $ticket->code }}<br>
                @if ($ticket->attendee_name)
                    Participante: {{ $ticket->attendee_name }}<br>
                @endif
                @if ($ticket->seat?->label)
                    Assento: {{ $ticket->seat->label }}@if ($ticket->seat->sector_name) - {{ $ticket->seat->sector_name }}@endif<br>
                @endif
            </li>
        @endforeach
    </ul>

    <p>
        <a href="{{ $trackingUrl }}" style="display:inline-block;padding:12px 20px;background:#2563eb;color:#fff;text-decoration:none;border-radius:6px;">
            Acompanhar venda
        </a>
    </p>

    <p>Se preferir, copie e cole este link no navegador:</p>
    <p>{{ $trackingUrl }}</p>
</body>
</html>
