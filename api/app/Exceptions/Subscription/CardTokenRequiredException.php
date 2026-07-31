<?php

namespace App\Exceptions\Subscription;

/**
 * Plano pago contratado/trocado sem `card_token` — decisão de produto
 * (2026-07-24): a contratação/troca de plano nunca mais redireciona ao
 * checkout do Mercado Pago; o cartão é sempre tokenizado no navegador
 * (MP.js) e enviado junto do request. Lançada ANTES de qualquer chamada ao
 * PSP (SubscriptionService::create/changePlan) — nunca cria um Preapproval
 * `pending` sem cartão associado.
 */
class CardTokenRequiredException extends \RuntimeException
{
}
