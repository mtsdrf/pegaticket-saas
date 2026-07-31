<?php

namespace App\Http\Requests\Legal;

use Illuminate\Foundation\Http\FormRequest;

class UpdateReleaseNoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:150'],
            'body' => ['required', 'string'],
            'version' => ['nullable', 'string', 'max:50'],
            'published_at' => ['nullable', 'date'],
        ];
    }
}
