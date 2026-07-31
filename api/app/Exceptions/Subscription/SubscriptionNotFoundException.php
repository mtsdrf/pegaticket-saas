<?php

namespace App\Exceptions\Subscription;

/**
 * Nenhuma assinatura encontrada para a operação pedida (cancelar/estornar
 * uma assinatura que não existe ou não pertence ao tenant ativo).
 */
class SubscriptionNotFoundException extends \RuntimeException
{
}
