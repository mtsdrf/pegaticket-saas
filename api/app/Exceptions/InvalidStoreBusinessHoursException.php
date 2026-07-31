<?php

namespace App\Exceptions;

/**
 * Guard defensivo de StoreBusinessHoursService::replaceForTenant() — o
 * payload já é validado pela UpdateStoreBusinessHoursRequest (7 dias
 * distintos 0-6), esta exception cobre chamadas diretas ao Service (fora
 * do HTTP) que não passem pela mesma validação.
 */
class InvalidStoreBusinessHoursException extends \RuntimeException
{
}
