<?php

namespace App\Exceptions\Payment;

/**
 * Qualquer falha ao chamar o provedor de pagamento (Mercado Pago hoje —
 * `MercadoPagoPaymentProvider`): chamada rejeitada pela API
 * (`assertSuccessful`), configuração ausente (payer_email/card_token/
 * preapproval_id não resolvidos) etc. Distinta de \RuntimeException
 * genérica pelo mesmo motivo de DuplicateNameException/
 * NoActivePreapprovalException: exceções HTTP do Symfony
 * (NotFoundHttpException/ModelNotFoundException) também estendem
 * \RuntimeException, então um `catch (\RuntimeException $e)` genérico no
 * Controller capturaria essas por engano.
 *
 * getMessage() carrega o código interno curto (ex.:
 * 'mercadopago.request_failed') — nunca é exibido ao usuário final direto;
 * o Controller que captura essa exceção sempre traduz para uma mensagem
 * amigável via `__('messages...')` antes de responder. O detalhe técnico
 * real (status HTTP, erro do MP) já foi logado pelo provider
 * (ApplicationLogger::error) antes de lançar.
 */
class PaymentProviderException extends \RuntimeException
{
    public function userMessage(): string
    {
        return match ($this->getMessage()) {
            'mercadopago.payer_collector_mismatch' => __('messages.payment.payer_collector_mismatch'),
            'mercadopago.card_authorization_failed' => __('messages.payment.card_authorization_failed'),
            default => __('messages.payment.provider_unavailable'),
        };
    }
}
