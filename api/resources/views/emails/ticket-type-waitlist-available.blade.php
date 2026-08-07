@extends('emails.layouts.base')

@section('preheader', 'O ingresso que você acompanhava voltou a ter vaga.')
@section('headline', 'Boa notícia: voltou a ter vaga')
@section('subheadline', 'O tipo de ingresso que você queria está disponível novamente. Como a procura pode ser alta, vale agir rápido.')

@section('content')
    <p style="margin:0 0 16px 0;">Olá{{ $waitlistEntry->name ? ', ' . $waitlistEntry->name : '' }}.</p>
    <p style="margin:0 0 16px 0;"><strong>{{ $ticketType->name }}</strong> voltou a ter vaga disponível.</p>
    <p style="margin:0 0 16px 0;">Como a procura costuma ser grande, recomendamos garantir o seu o quanto antes.</p>

    @include('emails.partials.button', ['url' => $storefrontUrl, 'label' => 'Garantir meu ingresso'])
    @include('emails.partials.link-box', ['url' => $storefrontUrl])
@endsection
