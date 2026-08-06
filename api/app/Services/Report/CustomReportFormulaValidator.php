<?php

namespace App\Services\Report;

use App\Exceptions\InvalidReportFormulaException;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;
use Symfony\Component\ExpressionLanguage\SyntaxError;

/**
 * Validação/avaliação de fórmula de métrica calculada do construtor de
 * relatórios personalizados (roadmap 5.6, requisito de segurança #2).
 *
 * Regras não-negociáveis:
 *  - `symfony/expression-language` puro, SEM `eval()`.
 *  - Nenhuma função é registrada no ExpressionLanguage (nem `abs`, nem
 *    `min/max`) — só os operadores aritméticos/lógicos nativos da
 *    linguagem de expressão ficam disponíveis. Uma fórmula tentando chamar
 *    qualquer `nome(...)` falha no parse com SyntaxError, pois a função
 *    não existe no registro.
 *  - As únicas variáveis aceitas são exatamente os nomes de métrica já
 *    whitelisted e selecionadas pelo relatório (resultado numérico já
 *    agregado pela query) — nunca uma métrica calculada referenciando
 *    outra (evita ciclo) nem qualquer identificador livre. `parse()`
 *    recebe a lista de nomes permitidos e o Symfony lança SyntaxError pra
 *    qualquer variável fora dela.
 *  - Charset pré-validado por regex antes mesmo de chamar o parser, como
 *    defesa em profundidade.
 */
class CustomReportFormulaValidator
{
    /**
     * Só dígitos, operadores aritméticos/comparação/lógicos básicos,
     * parênteses, ponto decimal, espaço e identificador de variável
     * ([A-Za-z_][A-Za-z0-9_]*). Qualquer aspas, backtick, colchete,
     * dois-pontos ou caractere fora deste conjunto é rejeitado antes de
     * chegar ao parser.
     */
    private const ALLOWED_CHARS_PATTERN = '/^[A-Za-z0-9_\.\+\-\*\/\(\)\s<>=!&|%,]+$/';

    private const MAX_FORMULA_LENGTH = 300;

    private ExpressionLanguage $expressionLanguage;

    public function __construct()
    {
        // Instância "crua", sem nenhum addFunction — nenhuma função vira
        // disponível na expressão, propositalmente.
        $this->expressionLanguage = new ExpressionLanguage;
    }

    /**
     * @param  array<int, string>  $allowedVariableNames  Nomes de métrica já
     *                                                    whitelisted e selecionadas pelo relatório.
     *
     * @throws InvalidReportFormulaException
     */
    public function validate(string $formula, array $allowedVariableNames): void
    {
        $formula = trim($formula);

        if ($formula === '') {
            throw new InvalidReportFormulaException('Fórmula vazia.');
        }

        if (mb_strlen($formula) > self::MAX_FORMULA_LENGTH) {
            throw new InvalidReportFormulaException('Fórmula excede o tamanho máximo permitido.');
        }

        if (! preg_match(self::ALLOWED_CHARS_PATTERN, $formula)) {
            throw new InvalidReportFormulaException('Fórmula contém caracteres não permitidos.');
        }

        try {
            $this->expressionLanguage->parse($formula, $allowedVariableNames);
        } catch (SyntaxError $e) {
            throw new InvalidReportFormulaException('Fórmula inválida: variável ou sintaxe não permitida.', 0, $e);
        }
    }

    /**
     * Avalia a fórmula já validada sobre uma linha de resultado agregado
     * (nunca linha-a-linha não agregada). Retorna null (nunca lança) em
     * erro de avaliação (ex.: divisão por zero) — quem chama decide como
     * representar "sem resultado" pro usuário, sem vazar detalhe interno.
     *
     * @param  array<string, float|int>  $variables
     */
    public function evaluate(string $formula, array $variables): ?float
    {
        try {
            $result = $this->expressionLanguage->evaluate($formula, $variables);
        } catch (\Throwable) {
            return null;
        }

        if (! is_numeric($result) || is_infinite((float) $result) || is_nan((float) $result)) {
            return null;
        }

        return (float) $result;
    }
}
