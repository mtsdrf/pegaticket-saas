<?php

namespace App\Exceptions\Payment;

/**
 * O tipo de pendência (`invoice_disputed`, `webhook_failed`) não tem uma
 * reconciliação ativa automatizável: fatura contestada exige decisão de
 * negócio humana (aceitar/rejeitar a contestação, nunca resolver sozinho
 * uma divergência de valor — regra 16 do agente de pagamentos) e webhook
 * com falha de processamento não tem uma "nova tentativa" seletiva sem
 * reconstruir o request original do PSP. Listados para visibilidade do
 * staff, mas o botão de reprocessar não se aplica.
 */
class PaymentIssueNotReprocessableException extends \RuntimeException
{
}
