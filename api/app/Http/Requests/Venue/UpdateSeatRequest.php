<?php

namespace App\Http\Requests\Venue;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSeatRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'sector_name' => ['nullable', 'string', 'max:255'],
            'label' => ['sometimes', 'string', 'max:255'],
            'kind' => ['sometimes', 'string', Rule::in(['mesa', 'assento', 'area', 'camarote'])],
            'capacity' => ['nullable', 'integer', 'min:1'],
            'pos_x' => ['nullable', 'numeric'],
            'pos_y' => ['nullable', 'numeric'],
            'is_accessible' => ['boolean'],
            'status' => ['sometimes', 'string', Rule::in(['disponivel', 'bloqueado', 'indisponivel'])],
        ];
    }
}
