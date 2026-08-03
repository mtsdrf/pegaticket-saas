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
            'pagbank.card_authorization_failed' => __('messages.payment.card_authorization_failed'),
            'pagbank.missing_payer_tax_id' => __('messages.payment.payer_tax_id_required'),
            'pagbank.missing_card_encrypted' => __('messages.payment.card_data_required'),
            'pagbank.missing_card_holder_name' => __('messages.payment.card_holder_name_required'),
            'pagbank.missing_card_holder_tax_id' => __('messages.payment.card_holder_tax_id_required'),
            'pagbank.missing_card_installments' => __('messages.payment.installments_required'),
            'pagbank.missing_debit_authentication' => __('messages.payment.debit_authentication_required'),
            'payment_method_not_supported' => __('messages.payment.method_not_supported'),
            default => __('messages.payment.provider_unavailable'),
        };
    }
}
