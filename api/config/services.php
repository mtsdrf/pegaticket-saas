<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'vapid' => [
        'public_key' => env('VAPID_PUBLIC_KEY'),
        'private_key' => env('VAPID_PRIVATE_KEY'),
        'subject' => env('VAPID_SUBJECT'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Pagamentos (roadmap Fase B, item 1 — PSP real)
    |--------------------------------------------------------------------------
    |
    | 'provider' escolhe o adapter ligado em PaymentProviderInterface (ver
    | AppServiceProvider) para o rail Clube->PegaTicket (assinatura —
    | SubscriptionService/InvoicePaymentService). 'manual' (default)
    | preserva o comportamento atual (sem PSP, conciliação manual).
    | 'mercadopago' liga o adapter real.
    |
    | 'sale_provider' é o mesmo tipo de seleção, mas SÓ para o rail
    | comprador->clube (venda de ingresso — SalePaymentService, binding
    | contextual em AppServiceProvider). Quando null/vazio (default),
    | herda o valor de 'provider' acima — mesmo comportamento de hoje,
    | onde os dois rails compartilhavam um único binding. Definir
    | SALE_PAYMENT_PROVIDER separadamente permite trocar só o rail de
    | venda (ex.: 'pagbank') sem tocar a assinatura.
    |
    */
    'payments' => [
        'provider' => env('PAYMENT_PROVIDER', 'manual'),
        'sale_provider' => env('SALE_PAYMENT_PROVIDER'),
    ],

    'mercadopago' => [
        // 'test' (default, seguro) ou 'production'. O Mercado Pago não
        // separa sandbox por URL — é o próprio par de credenciais que
        // determina o ambiente, então escolhemos aqui qual par usar.
        'environment' => env('MERCADOPAGO_ENVIRONMENT', 'test'),
        // Token de acesso da aplicação (painel MP > Suas integrações >
        // Testes/Produção > credenciais). Mantém compatibilidade com a
        // variável antiga (MERCADOPAGO_ACCESS_TOKEN) caso as novas não
        // estejam definidas.
        'access_token' => env('MERCADOPAGO_ENVIRONMENT', 'test') === 'production'
            ? env('MERCADOPAGO_ACCESS_TOKEN_PROD', env('MERCADOPAGO_ACCESS_TOKEN'))
            : env('MERCADOPAGO_ACCESS_TOKEN_TEST', env('MERCADOPAGO_ACCESS_TOKEN')),
        // Public key (mesma tela), usada só pelo frontend para tokenizar
        // cartão via MP.js — o backend nunca processa dado de cartão cru.
        'public_key' => env('MERCADOPAGO_ENVIRONMENT', 'test') === 'production'
            ? env('MERCADOPAGO_PUBLIC_KEY_PROD', env('MERCADOPAGO_PUBLIC_KEY'))
            : env('MERCADOPAGO_PUBLIC_KEY_TEST', env('MERCADOPAGO_PUBLIC_KEY')),
        // Override operacional só para homologação de assinatura/cobrança:
        // quando o ambiente estiver em "test", permite forçar um payer
        // conhecido da conta Mercado Pago sem depender do owner cadastrado
        // na empresa local.
        'test_payer_email' => env('MERCADOPAGO_TEST_PAYER_EMAIL'),
        // Chave secreta configurada em Suas integrações > Webhooks > Chave
        // secreta, usada para validar o header x-signature (HMAC-SHA256).
        'webhook_secret' => env('MERCADOPAGO_WEBHOOK_SECRET'),
        // TTL (segundos) do lock de idempotência persistida
        // (payment_idempotency_keys) — tempo suficiente para cobrir um
        // timeout de rede real (timeout HTTP do adapter é 15s) sem travar
        // o usuário por muito tempo se a tentativa original realmente
        // falhou rápido. Ver IdempotencyRepository::findOrCreatePending.
        'idempotency_lock_seconds' => (int) env('MERCADOPAGO_IDEMPOTENCY_LOCK_SECONDS', 120),
    ],

    /*
    |--------------------------------------------------------------------------
    | PagBank (rail comprador->clube — venda de ingresso)
    |--------------------------------------------------------------------------
    |
    | Adapter STUB (App\Services\Payment\PagBankPaymentProvider) — sem
    | credenciais reais nesta onda, a chamada HTTP à API do PagBank ainda
    | não existe (ver TODO PAGBANK REAL no adapter). Chaves abaixo já
    | preparadas para quando as credenciais reais existirem; até lá ficam
    | vazias e o adapter se comporta como o ManualPaymentProvider (cobrança
    | nasce 'pending', conciliação manual).
    |
    */
    'pagbank' => [
        'environment' => env('PAGBANK_ENVIRONMENT', 'sandbox'),
        'token' => env('PAGBANK_ENVIRONMENT', 'sandbox') === 'production'
            ? env('PAGBANK_TOKEN_PROD')
            : env('PAGBANK_TOKEN_SANDBOX'),
        'webhook_secret' => env('PAGBANK_WEBHOOK_SECRET'),
    ],

    'ifood' => [
        'base_url' => env('IFOOD_BASE_URL', 'https://merchant-api.ifood.com.br'),
        'oauth_token_url' => env('IFOOD_OAUTH_TOKEN_URL', 'https://merchant-api.ifood.com.br/authentication/v1.0/oauth/token'),
        'timeout_seconds' => (int) env('IFOOD_TIMEOUT_SECONDS', 15),
        'connect_timeout_seconds' => (int) env('IFOOD_CONNECT_TIMEOUT_SECONDS', 5),
        'paths' => [
            'merchant_list' => env('IFOOD_PATH_MERCHANT_LIST', '/merchant/v1.0/merchants'),
            'merchant_status' => env('IFOOD_PATH_MERCHANT_STATUS', '/merchant/v1.0/merchants/{merchantId}/status'),
            'merchant_interruptions' => env('IFOOD_PATH_MERCHANT_INTERRUPTIONS', '/merchant/v1.0/merchants/{merchantId}/interruptions'),
            'merchant_interruption_delete' => env('IFOOD_PATH_MERCHANT_INTERRUPTION_DELETE', '/merchant/v1.0/merchants/{merchantId}/interruptions/{interruptionId}'),
            'merchant_opening_hours' => env('IFOOD_PATH_MERCHANT_OPENING_HOURS', '/merchant/v1.0/merchants/{merchantId}/opening-hours'),
            'events_polling' => env('IFOOD_PATH_EVENTS_POLLING', '/events/v1.0/events:polling'),
            'events_acknowledgment' => env('IFOOD_PATH_EVENTS_ACKNOWLEDGMENT', '/events/v1.0/events/acknowledgment'),
            'order_details' => env('IFOOD_PATH_ORDER_DETAILS', '/order/v1.0/orders/{id}'),
            'order_cancellation_reasons' => env('IFOOD_PATH_ORDER_CANCELLATION_REASONS', '/order/v1.0/orders/{id}/cancellationReasons'),
            'catalog_categories' => env('IFOOD_PATH_CATALOG_CATEGORIES', '/catalog/v2.0/merchants/{merchantId}/categories'),
            'catalog_items' => env('IFOOD_PATH_CATALOG_ITEMS', '/catalog/v2.0/merchants/{merchantId}/items'),
            'catalog_batch' => env('IFOOD_PATH_CATALOG_BATCH', '/catalog/v2.0/merchants/{merchantId}/batch/{batchId}'),
            'actions' => [
                'confirm' => env('IFOOD_PATH_ACTION_CONFIRM', '/order/v1.0/orders/{id}/confirm'),
                'startPreparation' => env('IFOOD_PATH_ACTION_START_PREPARATION', '/order/v1.0/orders/{id}/startPreparation'),
                'readyToPickup' => env('IFOOD_PATH_ACTION_READY_TO_PICKUP', '/order/v1.0/orders/{id}/readyToPickup'),
                'dispatch' => env('IFOOD_PATH_ACTION_DISPATCH', '/order/v1.0/orders/{id}/dispatch'),
                'cancel' => env('IFOOD_PATH_ACTION_CANCEL', '/order/v1.0/orders/{id}/cancel'),
            ],
        ],
    ],

];
