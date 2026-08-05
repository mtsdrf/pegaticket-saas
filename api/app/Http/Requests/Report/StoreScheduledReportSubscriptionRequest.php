<?php

namespace App\Http\Requests\Report;

use App\Models\Report\ScheduledReportSubscription;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreScheduledReportSubscriptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'recipient_email' => ['required', 'email', 'max:190'],
            'frequency' => ['required', 'string', Rule::in(ScheduledReportSubscription::FREQUENCIES)],
        ];
    }
}
