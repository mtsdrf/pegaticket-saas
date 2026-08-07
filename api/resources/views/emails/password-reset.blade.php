@extends('emails.layouts.base')

@section('preheader', 'Recebemos uma solicitação para redefinir a senha da sua conta.')
@section('headline', 'Redefina sua senha com segurança')
@section('subheadline', 'Use o botão abaixo para criar uma nova senha e voltar a acessar sua conta com tranquilidade.')

@section('content')
    <p style="margin:0 0 16px 0;">Olá, {{ $user->name }}.</p>
    <p style="margin:0 0 16px 0;">Recebemos uma solicitação para redefinir a senha da sua conta no PegaTicket.</p>

    @include('emails.partials.button', ['url' => $resetUrl, 'label' => 'Redefinir senha'])
    @include('emails.partials.link-box', ['url' => $resetUrl])

    <div style="margin-top:20px;padding:16px 18px;border-radius:16px;background-color:#f2f8f8;border:1px solid #d9e8e6;">
        <div style="font-size:14px;line-height:1.6;color:#143b33;">Este link expira em <strong>1 hora</strong>.</div>
    </div>

    <p style="margin:20px 0 0 0;color:#5d7470;">Se você não solicitou essa redefinição, pode ignorar este e-mail. Sua senha atual continua sendo usada normalmente.</p>
@endsection
