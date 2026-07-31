<?php

namespace App\Http\Requests\Balcao;

use App\Models\Balcao\Station;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateStationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'type' => ['sometimes', 'required', Rule::in(Station::TYPES)],
            'is_active' => ['sometimes', 'required', 'boolean'],
        ];
    }
}
