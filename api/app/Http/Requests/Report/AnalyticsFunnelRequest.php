<?php

namespace App\Http\Requests\Report;

use Illuminate\Foundation\Http\FormRequest;

/** Funil de conversão (roadmap A2) — período opcional (default 30 dias) + evento opcional. */
class AnalyticsFunnelRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'event_uuid' => ['nullable', 'string', 'uuid'],
        ];
    }
}
