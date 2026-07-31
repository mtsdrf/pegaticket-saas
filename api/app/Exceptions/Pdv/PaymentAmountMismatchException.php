<?php

namespace App\Exceptions\Pdv;

/**
 * A soma das formas de pagamento informadas no fechamento da venda do PDV não
 * bate com o total do pedido (roadmap PDV, Fase PDV-1). A venda é rejeitada
 * inteira (transação revertida) — o pedido NUNCA fica marcado como pago sem a
 * soma bater. Mapeada para 422 PAYMENT_AMOUNT_MISMATCH no controller.
 */
class PaymentAmountMismatchException extends \Exception
{
}
