<?php

namespace App\Http\Requests\Route;

use Illuminate\Foundation\Http\FormRequest;

class ListRouteCandidatesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type' => ['required', 'in:delivery,collection'],
            'date' => ['required', 'date_format:Y-m-d'],
        ];
    }
}
