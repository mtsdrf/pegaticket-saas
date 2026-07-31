<?php

namespace App\Http\Requests\Group;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateGroupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $group = $this->route('group');

        return [
            'name' => ['sometimes', 'string', 'max:255', Rule::unique('groups', 'name')->ignore($group?->id)],
            'slug' => ['sometimes', 'string', 'max:100', Rule::unique('groups', 'slug')->ignore($group?->id)],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return __('messages.validation.messages');
    }
}