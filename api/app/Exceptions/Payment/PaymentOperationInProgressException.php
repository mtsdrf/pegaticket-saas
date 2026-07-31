<?php

namespace App\Exceptions\Payment;

/**
 * Lançada quando o guard de idempotência (`IdempotencyRepository`) detecta
 * que a MESMA operação financeira lógica (tenant+operation) já tem uma
 * tentativa em voo (`locked`) ou terminou em timeout ambíguo ainda não
 * reconciliado (`ambiguous`). Em ambos os casos o Mercado Pago NUNCA é
 * chamado — evita a cobrança/assinatura duplicada que motivou esta classe.
 *
 * Estende PaymentProviderException de propósito: os Controllers já
 * capturam esse tipo genericamente (ver api-patterns.md), então esta
 * subclasse é automaticamente tratada sem precisar editar todo call site.
 */
class PaymentOperationInProgressException extends PaymentProviderException
{
    public function userMessage(): string
    {
        return match ($this->getMessage()) {
            'payment.idempotency_ambiguous' => __('messages.payment.idempotency_ambiguous'),
            default => __('messages.payment.idempotency_locked'),
        };
    }
}
