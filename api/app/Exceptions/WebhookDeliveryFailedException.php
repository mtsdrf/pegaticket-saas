<?php

namespace App\Exceptions;

/**
 * Lançada por SendWebhookJob quando o endpoint do tenant responde com
 * status não-2xx ou a requisição falha (timeout/DNS/conexão recusada) —
 * sinaliza ao Laravel que o Job deve ser tentado de novo (respeitando
 * $tries/backoff), nunca capturada com \RuntimeException genérico.
 */
class WebhookDeliveryFailedException extends \RuntimeException
{
}
