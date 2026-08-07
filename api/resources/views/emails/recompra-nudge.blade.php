@extends('emails.layouts.base')

@section('preheader', 'Novos eventos disponíveis para você comprar novamente.')
@section('headline', 'Sentimos sua falta por aqui')
@section('subheadline', 'Seu histórico mostra que você já curtiu experiências com esta empresa. Aproveite para ver o que está disponível agora.')

@section('content')
    <p style="margin:0 0 16px 0;">Olá{{ $finalCustomer->name ? ', ' . $finalCustomer->name : '' }}.</p>
    <p style="margin:0 0 16px 0;">Faz um tempo que você não compra na <strong>{{ $tenant->name }}</strong> e queremos facilitar o seu retorno.</p>
    <p style="margin:0 0 16px 0;">Dê uma olhada nos próximos eventos e garanta seu ingresso antes que as vagas acabem.</p>

    @include('emails.partials.button', ['url' => $storefrontUrl, 'label' => 'Ver eventos da ' . $tenant->name])
    @include('emails.partials.link-box', ['url' => $storefrontUrl])
@endsection
