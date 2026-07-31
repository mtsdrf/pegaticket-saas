<?php

namespace App\Http\Requests\Venue;

use Illuminate\Foundation\Http\FormRequest;

class ListSeatRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:200'],
            'kind' => ['nullable', 'string', 'in:mesa,assento,area,camarote'],
            'sector_name' => ['nullable', 'string', 'max:255'],
        ];
    }
}
