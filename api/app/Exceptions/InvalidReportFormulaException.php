<?php

namespace App\Exceptions;

/**
 * Fórmula de métrica calculada do construtor de relatórios personalizados
 * (roadmap 5.6) rejeitada — variável fora da whitelist de métricas, função
 * não permitida, sintaxe inválida ou formula não avaliável (ex.: divisão
 * por zero). Nunca carrega a mensagem crua do Symfony ExpressionLanguage
 * pro usuário final; a controller sempre traduz pra `messages.custom_report.*`.
 */
class InvalidReportFormulaException extends \RuntimeException {}
