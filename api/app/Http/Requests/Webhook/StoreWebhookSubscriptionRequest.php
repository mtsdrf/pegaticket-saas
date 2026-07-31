<?php

namespace App\Http\Requests\Webhook;

use App\Services\Webhook\WebhookEventCatalog;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreWebhookSubscriptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'url' => ['required', 'string', 'url', 'max:2048'],
            'event_types' => ['required', 'array', 'min:1'],
            'event_types.*' => ['required', 'string', Rule::in(WebhookEventCatalog::SUPPORTED)],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
