<?php

namespace App\Exceptions\Pdv;

/**
 * Estado inválido de sessão de caixa (roadmap PDV, Fase PDV-1): já existe
 * sessão aberta pro registrador, sessão já fechada, ou nenhuma sessão aberta
 * pro tenant no momento da venda. Mapeada para 422 CASH_SESSION_ERROR no
 * controller, mesmo padrão de InvalidOrderStateException.
 */
class CashSessionException extends \Exception
{
}
