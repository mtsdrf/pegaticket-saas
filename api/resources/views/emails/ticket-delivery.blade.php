@extends('emails.layouts.base')

@section('preheader', 'Seus ingressos e dados da venda já estão disponíveis.')
@section('headline', 'Seu ingresso está pronto')
@section('subheadline', 'Centralizamos abaixo os dados principais da sua venda para facilitar o acompanhamento e o acesso ao evento.')

@section('content')
    <p style="margin:0 0 16px 0;">Olá.</p>

    @if ($mode === 'resent')
        <p style="margin:0 0 16px 0;">Reenviamos os dados do seu ingresso da venda <strong>#{{ $sale->codigo }}</strong>.</p>
    @elseif ($mode === 'reminder')
        <p style="margin:0 0 16px 0;">Seu evento está chegando. Aqui está o lembrete dos ingressos da venda <strong>#{{ $sale->codigo }}</strong>.</p>
    @elseif ($mode === 'transferred')
        <p style="margin:0 0 16px 0;">A titularidade de um ingresso da venda <strong>#{{ $sale->codigo }}</strong> foi transferida. O novo QR Code já está disponível e o anterior não é mais válido.</p>
    @else
        <p style="margin:0 0 16px 0;">Seu pagamento foi confirmado e os ingressos da venda <strong>#{{ $sale->codigo }}</strong> já estão prontos.</p>
    @endif

    <div style="margin:24px 0 18px 0;padding:18px;border-radius:18px;background-color:#f7fbfc;border:1px solid #d9e8e6;">
        <div style="font-size:14px;font-weight:700;color:#113d34;margin-bottom:14px;">Resumo dos ingressos</div>
        @foreach ($tickets as $ticket)
            <div style="{{ $loop->last ? '' : 'margin-bottom:14px;padding-bottom:14px;border-bottom:1px solid #d9e8e6;' }}">
                <div style="font-size:15px;font-weight:700;color:#113d34;margin-bottom:4px;">{{ $ticket->ticketType?->event?->name ?? 'Evento' }}</div>
                @if ($ticket->ticketType?->session?->name)
                    <div style="font-size:14px;color:#143b33;">Sessão: {{ $ticket->ticketType->session->name }}</div>
                @endif
                <div style="font-size:14px;color:#143b33;">Tipo: {{ $ticket->ticketType?->name ?? 'Ingresso' }}</div>
                <div style="font-size:14px;color:#143b33;">Código: {{ $ticket->code }}</div>
                @if ($ticket->attendee_name)
                    <div style="font-size:14px;color:#143b33;">Participante: {{ $ticket->attendee_name }}</div>
                @endif
                @if ($ticket->seat?->label)
                    <div style="font-size:14px;color:#143b33;">Assento: {{ $ticket->seat->label }}@if ($ticket->seat->sector_name) - {{ $ticket->seat->sector_name }}@endif</div>
                @endif
            </div>
        @endforeach
    </div>

    @include('emails.partials.button', ['url' => $trackingUrl, 'label' => 'Acompanhar venda'])
    @include('emails.partials.link-box', ['url' => $trackingUrl])
@endsection
