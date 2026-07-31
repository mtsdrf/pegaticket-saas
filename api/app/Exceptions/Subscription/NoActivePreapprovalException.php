<?php

namespace App\Exceptions\Subscription;

/**
 * A assinatura não tem Preapproval real no Mercado Pago (plano gratuito,
 * provider manual, ou cobrança ainda não iniciada) — não há o que trocar de
 * cartão. Distinta de \RuntimeException genérica (mesmo espírito de
 * InvalidSubscriptionTransitionException) para não ser capturada por engano
 * junto de exceções HTTP do Symfony.
 */
class NoActivePreapprovalException extends \RuntimeException
{
}
