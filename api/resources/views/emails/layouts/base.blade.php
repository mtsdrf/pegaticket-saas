@php
$resolvedPreheader = trim((string) ($preheader ?? $__env->yieldContent('preheader', '')));
$resolvedHeadline = trim((string) ($headline ?? $__env->yieldContent('headline', '')));
$resolvedSubheadline = trim((string) ($subheadline ?? $__env->yieldContent('subheadline', '')));
$resolvedFooterNote = trim((string) ($footerNote ?? $__env->yieldContent('footer_note', '')));
$logoUrl = rtrim((string) config('app.frontend_url'), '/') . '/logo.png';
$appName = (string) config('app.name', 'PegaTicket');
@endphp
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $appName }}</title>
</head>
<body style="margin:0;padding:0;background-color:#eef4f7;font-family:Arial,Helvetica,sans-serif;color:#143b33;">
    @if ($resolvedPreheader !== '')
        <div style="display:none;max-height:0;overflow:hidden;opacity:0;color:transparent;">
            {{ $resolvedPreheader }}
        </div>
    @endif

    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background-color:#eef4f7;margin:0;padding:24px 0;width:100%;">
        <tr>
            <td align="center" style="padding:0 16px;">
                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="max-width:640px;width:100%;">
                    <tr>
                        <td style="padding-bottom:16px;">
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background-color:#115441;border-radius:24px 24px 0 0;">
                                <tr>
                                    <td align="center" style="padding:28px 32px 20px 32px;">
                                        <table role="presentation" cellspacing="0" cellpadding="0" border="0" style="margin:0 auto;">
                                            <tr>
                                                <td style="vertical-align:middle;">
                                                    <img src="{{ $logoUrl }}" alt="{{ $appName }}" width="164" style="display:block;width:164px;max-width:100%;height:auto;border:0;">
                                                </td>
                                                <td style="vertical-align:middle;padding-left:18px;">
                                                    <div style="font-family:Sora,Inter,Arial,Helvetica,sans-serif;font-size:50px;line-height:1.05;font-weight:700;color:#ffffff;white-space:nowrap;">
                                                        PegaTicket
                                                    </div>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <tr>
                        <td>
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background-color:#ffffff;border:1px solid #d5e4ea;border-top:0;border-radius:0 0 24px 24px;box-shadow:0 18px 48px rgba(8,207,167,0.16);">
                                @if ($resolvedHeadline !== '' || $resolvedSubheadline !== '')
                                    <tr>
                                        <td style="padding:32px 32px 8px 32px;">
                                            @if ($resolvedHeadline !== '')
                                                <div style="font-size:28px;line-height:1.18;font-weight:700;color:#113d34;margin:0 0 10px 0;">
                                                    {{ $resolvedHeadline }}
                                                </div>
                                            @endif
                                            @if ($resolvedSubheadline !== '')
                                                <div style="font-size:15px;line-height:1.6;color:#5d7470;margin:0;">
                                                    {{ $resolvedSubheadline }}
                                                </div>
                                            @endif
                                        </td>
                                    </tr>
                                @endif

                                <tr>
                                    <td style="padding:{{ $resolvedHeadline !== '' || $resolvedSubheadline !== '' ? '16px 32px 20px 32px' : '32px' }};font-size:15px;line-height:1.7;color:#143b33;">
                                        @hasSection('content')
                                            @yield('content')
                                        @elseif (isset($contentHtml))
                                            {!! $contentHtml !!}
                                        @endif
                                    </td>
                                </tr>

                                <tr>
                                    <td style="padding:0 32px 32px 32px;">
                                        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="border-top:1px solid #dfe9ed;">
                                            <tr>
                                                <td style="padding-top:18px;font-size:12px;line-height:1.6;color:#78908c;">
                                                    {{ $resolvedFooterNote !== '' ? $resolvedFooterNote : 'Este e-mail foi enviado automaticamente pelo PegaTicket. Se precisar, acesse a plataforma para continuar o atendimento.' }}
                                                </td>
                                            </tr>
                                            <tr>
                                                <td style="padding-top:6px;font-size:12px;line-height:1.6;color:#9aaeb2;">
                                                    © {{ now()->year }} {{ $appName }}. Todos os direitos reservados.
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
