<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
</head>
<body style="font-family: Arial, sans-serif; color: #1a1a1a;">
    <p>Olá.</p>

    <p>Segue o resumo {{ $frequencyLabel }} da operação de <strong>{{ $tenant->name }}</strong>.</p>

    <table cellpadding="8" cellspacing="0" style="border-collapse: collapse; width: 100%; max-width: 480px;">
        <tr style="background:#f3f4f6;">
            <td style="border:1px solid #e5e7eb;"><strong>Vendas totais</strong></td>
            <td style="border:1px solid #e5e7eb;">{{ $indicators['total_sales'] ?? '—' }}</td>
        </tr>
        <tr>
            <td style="border:1px solid #e5e7eb;"><strong>Faturamento total</strong></td>
            <td style="border:1px solid #e5e7eb;">R$ {{ $indicators['total_sales_amount'] ?? '0,00' }}</td>
        </tr>
        <tr style="background:#f3f4f6;">
            <td style="border:1px solid #e5e7eb;"><strong>Ticket médio</strong></td>
            <td style="border:1px solid #e5e7eb;">R$ {{ $indicators['average_ticket'] ?? '0,00' }}</td>
        </tr>
        <tr>
            <td style="border:1px solid #e5e7eb;"><strong>Valor recebido</strong></td>
            <td style="border:1px solid #e5e7eb;">R$ {{ $indicators['amount_received'] ?? '0,00' }}</td>
        </tr>
        <tr style="background:#f3f4f6;">
            <td style="border:1px solid #e5e7eb;"><strong>A receber</strong></td>
            <td style="border:1px solid #e5e7eb;">R$ {{ $indicators['amount_receivable'] ?? '0,00' }}</td>
        </tr>
        <tr style="background:#f3f4f6;">
            <td style="border:1px solid #e5e7eb;"><strong>Crescimento vs. período anterior</strong></td>
            <td style="border:1px solid #e5e7eb;">{{ $indicators['sales_growth_percentage'] ?? 0 }}%</td>
        </tr>
    </table>

    <p style="margin-top:20px;">
        <a href="{{ $dashboardUrl }}" style="display:inline-block;padding:12px 20px;background:#2563eb;color:#fff;text-decoration:none;border-radius:6px;">
            Ver painel completo
        </a>
    </p>
</body>
</html>
