<?php

namespace App\Exceptions;

/**
 * Ação de caixa inválida para o estado atual (tentar abrir com um já
 * aberto, tentar fechar sem nenhum aberto) — mesmo motivo de
 * InvalidSaleStateException para não misturar com \RuntimeException
 * genérica (abort()/ModelNotFoundException também estendem
 * \RuntimeException).
 */
class InvalidCashSessionStateException extends \RuntimeException {}
