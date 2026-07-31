<?php

namespace App\Exceptions\Payment;

/**
 * Referência informada pelo staff no reprocessamento manual
 * (`PaymentIssueController::reprocess`) não corresponde a nenhuma
 * pendência elegível do tipo indicado — reference inexistente, tipo
 * incorreto, ou item que já deixou de ser uma pendência (ex.: idempotência
 * cujo lock ainda está ativo, ou já foi resolvida por outra reconciliação
 * entre a listagem e o clique do staff).
 */
class PaymentIssueNotFoundException extends \RuntimeException
{
}
