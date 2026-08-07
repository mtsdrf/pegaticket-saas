@extends('emails.layouts.base')

@section('preheader', 'Resumo agendado da operação da sua empresa.')
@section('headline', 'Resumo ' . $frequencyLabel . ' da operação')
@section('subheadline', 'Acompanhe os principais indicadores de ' . $tenant->name . ' em um formato rápido para tomada de decisão.')

@section('content')
    <p style="margin:0 0 16px 0;">Olá.</p>
    <p style="margin:0 0 12px 0;">Segue o resumo {{ $frequencyLabel }} da operação de <strong>{{ $tenant->name }}</strong>.</p>

    @include('emails.partials.metric-table', [
        'rows' => [
            ['label' => 'Vendas totais', 'value' => (string) ($indicators['total_sales'] ?? '—')],
            ['label' => 'Faturamento total', 'value' => 'R$ ' . ($indicators['total_sales_amount'] ?? '0,00')],
            ['label' => 'Ticket médio', 'value' => 'R$ ' . ($indicators['average_ticket'] ?? '0,00')],
            ['label' => 'Valor recebido', 'value' => 'R$ ' . ($indicators['amount_received'] ?? '0,00')],
            ['label' => 'A receber', 'value' => 'R$ ' . ($indicators['amount_receivable'] ?? '0,00')],
            ['label' => 'Crescimento vs. período anterior', 'value' => ($indicators['sales_growth_percentage'] ?? 0) . '%'],
        ],
    ])

    @include('emails.partials.button', ['url' => $dashboardUrl, 'label' => 'Ver painel completo'])
    @include('emails.partials.link-box', ['url' => $dashboardUrl, 'label' => 'Se quiser abrir o painel manualmente, use este link:'])
@endsection
