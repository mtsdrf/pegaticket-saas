@extends('emails.layouts.base')

@section('preheader', 'Confirme a alteração do e-mail da sua conta no sistema.')
@section('headline', 'Confirme seu novo e-mail')
@section('subheadline', 'Antes de concluir a troca, precisamos validar que o novo endereço realmente pertence a você.')

@section('content')
    <p style="margin:0 0 16px 0;">Olá, {{ $user->name }}.</p>
    <p style="margin:0 0 16px 0;">Recebemos uma solicitação para trocar o e-mail da sua conta no PegaTicket para <strong>{{ $newEmail }}</strong>.</p>

    @include('emails.partials.button', ['url' => $confirmUrl, 'label' => 'Confirmar novo e-mail'])
    @include('emails.partials.link-box', ['url' => $confirmUrl])

    <div style="margin-top:20px;padding:16px 18px;border-radius:16px;background-color:#f2f8f8;border:1px solid #d9e8e6;">
        <div style="font-size:14px;line-height:1.6;color:#143b33;">Este link expira em <strong>24 horas</strong>.</div>
    </div>

    <p style="margin:20px 0 0 0;color:#5d7470;">Se você não solicitou essa troca, pode ignorar este e-mail. Seu e-mail atual continua sendo usado normalmente.</p>
@endsection
