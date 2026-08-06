<?php

namespace App\Exceptions;

/**
 * Falha ao executar um relatório personalizado (roadmap 5.6) já validado —
 * erro de banco, timeout, etc. Sempre envolve a exceção original só para
 * log interno (`previous`); a mensagem pública é sempre genérica
 * (`messages.custom_report.execution_failed`), nunca a mensagem/stack
 * trace do driver de banco.
 */
class ReportExecutionException extends \RuntimeException {}
