@extends('emails.layouts.base')

@section('preheader', 'Você recebeu um convite para acessar uma empresa no PegaTicket.')
@section('headline', 'Você recebeu um convite')
@section('subheadline', 'Confirme seu acesso para começar a operar junto com o time na plataforma.')

@section('content')
    <p style="margin:0 0 16px 0;">Olá, {{ $invite->name }}.</p>
    <p style="margin:0 0 16px 0;">Você foi convidado(a) para fazer parte da empresa <strong>{{ $invite->tenant->name }}</strong> no PegaTicket.</p>

    @include('emails.partials.button', ['url' => $inviteUrl, 'label' => 'Aceitar convite'])
    @include('emails.partials.link-box', ['url' => $inviteUrl])

    <div style="margin-top:20px;padding:16px 18px;border-radius:16px;background-color:#f2f8f8;border:1px solid #d9e8e6;">
        <div style="font-size:14px;line-height:1.6;color:#143b33;">Este convite expira em <strong>{{ $invite->expires_at->format('d/m/Y H:i') }}</strong>.</div>
    </div>

    <p style="margin:20px 0 0 0;color:#5d7470;">Se você não esperava este convite, pode ignorar este e-mail.</p>
@endsection
