<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
</head>
<body style="font-family: Arial, sans-serif; color: #1a1a1a;">
    <p>Olá{{ $finalCustomer->name ? ', ' . $finalCustomer->name : '' }}.</p>

    <p>Faz um tempo que você não compra na <strong>{{ $tenant->name }}</strong> — sentimos sua falta!</p>

    <p>Dá uma olhada nos próximos eventos e garanta seu ingresso antes que esgote.</p>

    <p>
        <a href="{{ $storefrontUrl }}" style="display:inline-block;padding:12px 20px;background:#2563eb;color:#fff;text-decoration:none;border-radius:6px;">
            Ver eventos da {{ $tenant->name }}
        </a>
    </p>

    <p>Se preferir, copie e cole este link no navegador:</p>
    <p>{{ $storefrontUrl }}</p>
</body>
</html>
