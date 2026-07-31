<?php

namespace App\Exceptions\Balcao;

/**
 * Estado inválido de comanda / item de comanda (roadmap Balcão, Fases 1+2):
 * comanda já fechada/cancelada, fechamento duplo, transição de prep_status
 * inválida, cancelamento sem motivo, ausência de local de estoque etc.
 * Mapeada para 422 COMANDA_ERROR no controller, mesmo padrão de
 * InvalidOrderStateException / CashSessionException.
 */
class ComandaException extends \Exception
{
}
