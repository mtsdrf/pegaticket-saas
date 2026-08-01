<?php

namespace App\Exceptions;

/**
 * CEP com formato inválido (diferente de 8 dígitos) ou não encontrado pelo
 * ViaCEP (`erro: true` na resposta) — distinta de \RuntimeException
 * genérica pelo mesmo motivo de InvalidSaleStateException.
 */
class CepNotFoundException extends \RuntimeException
{
}
