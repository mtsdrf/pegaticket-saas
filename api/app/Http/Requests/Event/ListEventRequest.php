<?php

namespace App\Http\Requests\Event;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'sort_by' => ['nullable', 'string', 'in:name,starts_at,status'],
            'sort_dir' => ['nullable', 'string', 'in:asc,desc'],
            'q' => ['nullable', 'string', 'max:255'],
            'name' => ['nullable', 'string', 'max:255'],
            'event_category_uuid' => ['nullable', 'uuid'],
            'type' => ['nullable', 'string', Rule::in(['ingresso', 'inscricao', 'mesa', 'assento', 'misto'])],
            'status' => ['nullable', 'string', Rule::in(['rascunho', 'agendado', 'publicado', 'vendas_pausadas', 'esgotado', 'encerrado', 'cancelado', 'arquivado'])],
            'visibility' => ['nullable', 'string', Rule::in(['public', 'hidden', 'private', 'exclusive'])],
        ];
    }
}
