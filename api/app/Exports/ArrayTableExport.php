<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Export XLSX genérico e reutilizável (roadmap A2, decisão 2026-08-05 §7.1
 * item 2 do roadmap de refatoração: `maatwebsite/excel`, modo síncrono no
 * MVP) — recebe cabeçalhos + linhas já formatados pelo Service que gerou o
 * relatório (mesma fonte de dado do CSV existente), sem acoplar a lib de
 * planilha a nenhum domínio específico.
 */
class ArrayTableExport implements FromArray, ShouldAutoSize, WithHeadings, WithStyles
{
    /**
     * @param  array<int, array<int, string|int|float|null>>  $rows
     * @param  array<int, string>  $headings
     */
    public function __construct(
        private array $headings,
        private array $rows,
    ) {}

    public function array(): array
    {
        return $this->rows;
    }

    public function headings(): array
    {
        return $this->headings;
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
