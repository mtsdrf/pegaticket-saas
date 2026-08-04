<?php

namespace App\Http\Requests\Affiliate;

use App\Models\Affiliate\Affiliate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAffiliateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $affiliate = Affiliate::where('tenant_id', app('tenant_id'))
            ->where('uuid', $this->route('uuid'))
            ->first();

        return [
            'name' => ['required', 'string', 'max:150'],
            'email' => ['nullable', 'email', 'max:180'],
            'tracking_code' => [
                'required',
                'string',
                'max:40',
                'alpha_dash',
                Rule::unique('affiliates', 'tracking_code')
                    ->where('tenant_id', app('tenant_id'))
                    ->whereNull('deleted_at')
                    ->ignore($affiliate?->id),
            ],
            'commission_percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'status' => ['nullable', 'string', Rule::in(['active', 'inactive'])],
        ];
    }
}
