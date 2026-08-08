@extends('emails.layouts.base')

@section('preheader', $isEnabled ? 'Seus recebimentos estão prontos.' : 'Precisamos de uma informação sobre seus recebimentos.')
@section('headline', $isEnabled ? 'Seus recebimentos estão prontos' : 'Precisamos de uma informação')

@section('content')
    <p style="margin:0 0 16px 0;">Olá, {{ $tenantName }}.</p>
    @if ($isEnabled)
        <p style="margin:0 0 16px 0;">Sua conta de recebimentos foi habilitada. Você já pode publicar e vender ingressos pagos.</p>
    @else
        <p style="margin:0 0 16px 0;">Encontramos uma pendência na configuração dos seus recebimentos. Acesse as configurações para revisar e resolver.</p>
    @endif

    @include('emails.partials.button', ['url' => $settingsUrl, 'label' => 'Ver recebimentos'])
    @include('emails.partials.link-box', ['url' => $settingsUrl])
@endsection
