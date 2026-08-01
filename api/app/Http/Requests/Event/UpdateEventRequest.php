<?php

namespace App\Http\Requests\Event;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'slug' => [
                'sometimes',
                'string',
                'max:255',
                Rule::unique('events', 'slug')
                    ->where(function ($query) {
                        $query->where('tenant_id', app('tenant_id'))->whereNull('deleted_at');
                    })
                    ->ignore($this->route('event')?->id),
            ],
            'event_category_uuid' => [
                'nullable',
                'uuid',
                Rule::exists('event_categories', 'uuid')->where(function ($query) {
                    $query->where('tenant_id', app('tenant_id'))->whereNull('deleted_at');
                }),
            ],
            'venue_uuid' => [
                'nullable',
                'uuid',
                Rule::exists('venues', 'uuid')->where(function ($query) {
                    $query->where('tenant_id', app('tenant_id'))->whereNull('deleted_at');
                }),
            ],
            'description_short' => ['nullable', 'string', 'max:255'],
            'description_full' => ['nullable', 'string'],
            'cover_image' => ['nullable', 'image', 'max:5120'],
            'type' => ['sometimes', 'string', Rule::in(['ingresso', 'inscricao', 'mesa', 'assento', 'misto'])],
            'location_name' => ['nullable', 'string', 'max:255'],
            'location_address' => ['nullable', 'string', 'max:255'],
            'location_lat' => ['nullable', 'numeric', 'between:-90,90'],
            'location_lng' => ['nullable', 'numeric', 'between:-180,180'],
            'starts_at' => ['sometimes', 'date'],
            'ends_at' => ['sometimes', 'date', 'after_or_equal:starts_at'],
            'visibility' => ['sometimes', 'string', Rule::in(['public', 'hidden', 'private', 'exclusive'])],
            'status' => ['sometimes', 'string', Rule::in(['rascunho', 'agendado', 'publicado', 'vendas_pausadas', 'esgotado', 'encerrado', 'cancelado', 'arquivado'])],
        ];
    }

    public function messages(): array
    {
        return [
            'event_category_uuid.exists' => __('messages.event.invalid_category'),
            'venue_uuid.exists' => __('messages.event.invalid_venue'),
            'slug.unique' => __('messages.event.slug_exists'),
        ];
    }
}
