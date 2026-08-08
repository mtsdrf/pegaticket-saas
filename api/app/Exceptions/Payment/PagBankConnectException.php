<?php

namespace App\Exceptions\Payment;

/**
 * Falha no fluxo OAuth Connect do PagBank (state inválido/expirado,
 * troca de code por token rejeitada, renovação ou revogação falhando).
 * Distinta de \RuntimeException genérica pelo mesmo motivo de
 * PaymentProviderException — exceções HTTP do Symfony também estendem
 * \RuntimeException.
 */
class PagBankConnectException extends \RuntimeException
{
    public function userMessage(): string
    {
        return match ($this->getMessage()) {
            'pagbank_connect.invalid_state' => __('messages.pagbank_connect.invalid_state'),
            'pagbank_connect.token_exchange_failed' => __('messages.pagbank_connect.token_exchange_failed'),
            'pagbank_connect.refresh_failed' => __('messages.pagbank_connect.refresh_failed'),
            'pagbank_connect.revoke_failed' => __('messages.pagbank_connect.revoke_failed'),
            'pagbank_connect.not_connected' => __('messages.pagbank_connect.not_connected'),
            default => __('messages.pagbank_connect.unavailable'),
        };
    }
}
