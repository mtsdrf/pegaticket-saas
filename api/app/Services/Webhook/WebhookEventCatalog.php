<?php

namespace App\Services\Webhook;

/**
 * Lista única de event_types suportados no MVP (roadmap A6, item 20) —
 * usada tanto na validação de StoreWebhookSubscriptionRequest/
 * UpdateWebhookSubscriptionRequest quanto como referência pros listeners
 * DispatchWebhookOnOrder* em app/Listeners/Webhook/. Escopo inicial: só
 * eventos de pedido (criado, mudança de status, cancelado/aprovado/
 * rejeitado), conforme roadmap. Adicionar um evento novo aqui exige criar
 * o listener correspondente e registrá-lo no EventServiceProvider.
 */
class WebhookEventCatalog
{
    public const SUPPORTED = [
        'order.created',
        'order.approved',
        'order.rejected',
        'order.delivered',
        'order.cancelled',
        'order.paid',
    ];
}
