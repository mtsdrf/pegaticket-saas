<?php

namespace App\Console\Commands\Support;

/**
 * Parser dedicado de dump mysqldump (`INSERT INTO \`table\` (\`col\`, ...)
 * VALUES (...), (...), ...;`), sem depender de subir um servidor MySQL nem
 * de `str_getcsv`/regex ingênuo — campos de texto livre do legado (ex.:
 * `venda.observacao`) podem conter vírgula e quebra de linha dentro da
 * própria string, o que quebraria um split por linha/vírgula simples.
 *
 * Varre o arquivo inteiro em memória (23MB, folga confortável) e, para cada
 * tabela pedida, localiza cada bloco `INSERT INTO ... VALUES ...;` (um
 * dump grande tem vários blocos por tabela, em lotes de ~250-300 linhas) e
 * tokeniza caractere-a-caractere dentro dele, respeitando aspas simples e
 * escape de backslash (`\'`, `\\\\`, `\n`, `\r`, `\t`, `\0`) — mesma lógica
 * já validada em Python antes desta implementação PHP.
 */
class LegacyDumpParser
{
    private string $sql;
    private int $len;

    public function __construct(string $path)
    {
        $this->sql = file_get_contents($path);
        $this->len = strlen($this->sql);
    }

    /**
     * @return iterable<int, array<string, mixed>>
     */
    public function rows(string $table): iterable
    {
        $pattern = '/INSERT INTO `' . preg_quote($table, '/') . '`\s*\(([^)]*)\)\s*VALUES\s*/i';
        $offset = 0;

        while (preg_match($pattern, $this->sql, $m, PREG_OFFSET_CAPTURE, $offset)) {
            $columns = array_map(
                static fn (string $c): string => trim($c, " \t\r\n`"),
                explode(',', $m[1][0])
            );

            $pos = $m[0][1] + strlen($m[0][0]);

            while (true) {
                $pos = $this->skipWhitespaceAndCommas($pos);

                if ($pos >= $this->len) {
                    break;
                }

                if ($this->sql[$pos] === ';') {
                    $pos++;
                    break;
                }

                if ($this->sql[$pos] !== '(') {
                    // Formato inesperado — para de tentar ler este bloco,
                    // segue para o próximo `INSERT INTO` casado pelo regex.
                    break;
                }

                [$values, $pos] = $this->parseTuple($pos);

                yield array_combine($columns, $values);
            }

            $offset = $pos;
        }
    }

    private function skipWhitespaceAndCommas(int $pos): int
    {
        while ($pos < $this->len && (ctype_space($this->sql[$pos]) || $this->sql[$pos] === ',')) {
            $pos++;
        }

        return $pos;
    }

    /**
     * @return array{0: array<int, mixed>, 1: int}
     */
    private function parseTuple(int $pos): array
    {
        $pos++; // consome '('
        $values = [];

        while (true) {
            while ($pos < $this->len && ctype_space($this->sql[$pos])) {
                $pos++;
            }

            if ($pos >= $this->len) {
                break;
            }

            if ($this->sql[$pos] === ')') {
                $pos++;
                break;
            }

            if ($this->sql[$pos] === "'") {
                [$value, $pos] = $this->parseString($pos);
            } else {
                [$value, $pos] = $this->parseLiteral($pos);
            }

            $values[] = $value;

            while ($pos < $this->len && ctype_space($this->sql[$pos])) {
                $pos++;
            }

            if ($pos < $this->len && $this->sql[$pos] === ',') {
                $pos++;
                continue;
            }

            if ($pos < $this->len && $this->sql[$pos] === ')') {
                $pos++;
            }

            break;
        }

        return [$values, $pos];
    }

    /**
     * @return array{0: string, 1: int}
     */
    private function parseString(int $pos): array
    {
        $pos++; // consome aspa de abertura
        $out = '';

        $escapeMap = [
            "'" => "'",
            '"' => '"',
            '\\' => '\\',
            'n' => "\n",
            'r' => "\r",
            't' => "\t",
            '0' => "\0",
            'Z' => "\x1a",
            'b' => "\x08",
        ];

        while ($pos < $this->len) {
            $ch = $this->sql[$pos];

            if ($ch === '\\' && $pos + 1 < $this->len) {
                $next = $this->sql[$pos + 1];
                $out .= $escapeMap[$next] ?? $next;
                $pos += 2;
                continue;
            }

            if ($ch === "'") {
                // mysqldump também pode escapar aspa dobrando ('') em vez
                // de barra — cobre os dois estilos.
                if ($pos + 1 < $this->len && $this->sql[$pos + 1] === "'") {
                    $out .= "'";
                    $pos += 2;
                    continue;
                }

                $pos++; // consome aspa de fechamento
                break;
            }

            $out .= $ch;
            $pos++;
        }

        return [$out, $pos];
    }

    /**
     * @return array{0: int|float|string|null, 1: int}
     */
    private function parseLiteral(int $pos): array
    {
        $start = $pos;

        while ($pos < $this->len && $this->sql[$pos] !== ',' && $this->sql[$pos] !== ')' && !ctype_space($this->sql[$pos])) {
            $pos++;
        }

        $literal = substr($this->sql, $start, $pos - $start);

        if (strcasecmp($literal, 'NULL') === 0) {
            return [null, $pos];
        }

        if (is_numeric($literal)) {
            return [str_contains($literal, '.') ? (float) $literal : (int) $literal, $pos];
        }

        return [$literal, $pos];
    }
}
