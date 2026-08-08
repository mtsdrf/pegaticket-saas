<?php

namespace App\Exceptions\Payment;

/**
 * Falha no caminho Account/Cadastro do PagBank (R2.2) — criação de conta
 * SELLER rejeitada, tentativa de criação duplicada, ou consulta de
 * status falhando. Distinta de \RuntimeException genérica pelo mesmo
 * motivo de PagBankConnectException.
 */
class PagBankAccountException extends \RuntimeException
{
    public function userMessage(): string
    {
        return match ($this->getMessage()) {
            'pagbank_account.already_submitted' => __('messages.pagbank_account.already_submitted'),
            'pagbank_account.creation_failed' => __('messages.pagbank_account.creation_failed'),
            'pagbank_account.sync_failed' => __('messages.pagbank_account.sync_failed'),
            'pagbank_account.no_account' => __('messages.pagbank_account.no_account'),
            default => __('messages.pagbank_account.unavailable'),
        };
    }
}
