<?php

namespace App\Exceptions;

/**
 * Definição de relatório personalizado (roadmap 5.6) rejeitada: dimensão ou
 * métrica fora da whitelist, fonte de dados desconhecida, ou limite de
 * complexidade (máx. dimensões/métricas/filtros) excedido. Controller
 * traduz sempre para HTTP 422 com mensagem i18n, nunca expõe a mensagem
 * técnica crua construída aqui (`getMessage()` é só para log).
 */
class InvalidReportDefinitionException extends \RuntimeException
{
    public function __construct(string $message, private readonly string $errorCode = 'INVALID_REPORT_DEFINITION')
    {
        parent::__construct($message);
    }

    public function errorCode(): string
    {
        return $this->errorCode;
    }
}
