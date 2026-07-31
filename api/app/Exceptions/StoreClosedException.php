<?php

namespace App\Exceptions;

/**
 * Guard novo do checkout público (roadmap Delivery, Fase 2) — loja fechada
 * no horário atual (StoreBusinessHoursService::isOpenNow()). Mesmo espírito
 * de InsufficientStockException: distinta de \RuntimeException genérica
 * para não ser capturada por engano junto de exceções HTTP do Symfony.
 */
class StoreClosedException extends \RuntimeException
{
}
