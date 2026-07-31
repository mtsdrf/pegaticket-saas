<?php

namespace App\Http\Requests\Product;

use App\Services\Product\ProductImportService;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Só valida o SHAPE do payload (array de linhas, tamanho). Regra de
 * negócio por campo (nome obrigatório, preço numérico, tipo/categoria,
 * sku duplicado...) é responsabilidade de ProductImportService::commit(),
 * que revalida cada linha do zero e apenas PULA a linha inválida em vez
 * de rejeitar o payload inteiro — condizente com "reenvio do preview, mas
 * nunca confiar só nele".
 */
class ProductImportCommitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'rows' => ['required', 'array', 'min:1', 'max:' . ProductImportService::MAX_ROWS],
            'rows.*.nome' => ['nullable', 'string', 'max:255'],
            'rows.*.categoria' => ['nullable', 'string', 'max:255'],
            'rows.*.tipo' => ['nullable', 'string', 'max:255'],
            // preco/disponivel aceitam string OU escalar (o frontend pode
            // reenviar como veio do preview, string "12,90"/"sim", ou já
            // tipado, float 12.9/bool true) — a normalização real acontece
            // no Service.
            'rows.*.preco' => ['nullable'],
            'rows.*.descricao' => ['nullable', 'string'],
            'rows.*.sku' => ['nullable', 'string', 'max:255'],
            'rows.*.disponivel' => ['nullable'],
        ];
    }
}
