<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
</head>
<body style="font-family: Arial, sans-serif; color: #1a1a1a;">
    <p>Olá{{ $waitlistEntry->name ? ', ' . $waitlistEntry->name : '' }}.</p>

    <p>Boa notícia: <strong>{{ $ticketType->name }}</strong> voltou a ter vaga disponível.</p>

    <p>Como a procura costuma ser grande, recomendamos garantir o seu o quanto antes.</p>

    <p>
        <a href="{{ $storefrontUrl }}" style="display:inline-block;padding:12px 20px;background:#2563eb;color:#fff;text-decoration:none;border-radius:6px;">
            Garantir meu ingresso
        </a>
    </p>

    <p>Se preferir, copie e cole este link no navegador:</p>
    <p>{{ $storefrontUrl }}</p>
</body>
</html>
