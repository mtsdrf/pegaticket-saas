<?php

namespace App\Http\Requests\Product;

use Illuminate\Foundation\Http\FormRequest;

class ProductImportPreviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Tamanho pequeno o bastante pra rejeitar upload absurdo antes
            // do parsing; o limite real de linhas é checado no Service
            // (ProductImportService::MAX_ROWS), não aqui.
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:5120'],
        ];
    }
}
