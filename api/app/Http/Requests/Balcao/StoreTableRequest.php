<?php

namespace App\Http\Requests\Balcao;

use App\Models\Balcao\Table;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTableRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'label' => ['required', 'string', 'max:255'],
            'area' => ['nullable', 'string', 'max:255'],
            'seats' => ['nullable', 'integer', 'min:1'],
            'status' => ['nullable', Rule::in(Table::STATUSES)],
        ];
    }
}
