<?php

namespace App\Exceptions\Subscription;

/**
 * Transição de estado de assinatura não permitida pela
 * SubscriptionStateMachine. Distinta de \RuntimeException genérica (mesmo
 * espírito de DuplicateNameException) para não ser capturada por engano
 * junto de exceções HTTP do Symfony.
 */
class InvalidSubscriptionTransitionException extends \RuntimeException
{
}
