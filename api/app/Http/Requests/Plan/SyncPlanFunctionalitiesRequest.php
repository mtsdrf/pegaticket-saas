<?php

namespace App\Http\Requests\Plan;

use Illuminate\Foundation\Http\FormRequest;

class SyncPlanFunctionalitiesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'functionalities' => ['required', 'array'],
            'functionalities.*' => ['required', 'string'],
        ];
    }
}
