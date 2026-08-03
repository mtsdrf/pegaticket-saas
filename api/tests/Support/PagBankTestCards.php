<?php

namespace Tests\Support;

use RuntimeException;

trait PagBankTestCards
{
    /**
     * @return array<int, array{brand:string,number:string,cvv:string,expiration:string,encrypted:string}>
     */
    protected static function pagBankSandboxCards(): array
    {
        $fixturePath = dirname(__DIR__, 3).'/cartoes_teste_pagbank.txt';

        if (! is_file($fixturePath)) {
            throw new RuntimeException("Arquivo de cartões PagBank não encontrado em {$fixturePath}");
        }

        $contents = trim((string) file_get_contents($fixturePath));

        if ($contents === '') {
            throw new RuntimeException('Arquivo de cartões PagBank está vazio.');
        }

        $blocks = preg_split("/\n\s*\n/", $contents) ?: [];
        $cards = [];

        foreach ($blocks as $block) {
            $card = [];

            foreach (preg_split("/\n/", trim($block)) ?: [] as $line) {
                [$rawLabel, $rawValue] = array_pad(explode(':', $line, 2), 2, null);
                $label = trim((string) $rawLabel);
                $value = trim((string) $rawValue);

                if ($label === '' || $value === '') {
                    continue;
                }

                $card[$label] = $value;
            }

            if (
                isset($card['Bandeira'], $card['Número'], $card['CVV'], $card['Expiração'], $card['Criptografia'])
            ) {
                $cards[] = [
                    'brand' => $card['Bandeira'],
                    'number' => $card['Número'],
                    'cvv' => $card['CVV'],
                    'expiration' => $card['Expiração'],
                    'encrypted' => $card['Criptografia'],
                ];
            }
        }

        if ($cards === []) {
            throw new RuntimeException('Nenhum cartão válido foi encontrado no arquivo de homologação do PagBank.');
        }

        return $cards;
    }
}
