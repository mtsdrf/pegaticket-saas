<?php

namespace App\Http\Requests\Balcao;

use App\Models\Balcao\Station;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreStationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'type' => ['nullable', Rule::in(Station::TYPES)],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
