<?php

namespace App\Http\Requests\Storefront;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ProductPromotionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (!$this->filled('discount_type')) {
            $this->merge(['discount_type' => 'fixed_price']);
        }
    }

    public function rules(): array
    {
        return [
            'ticket_type_uuid' => [
                'required',
                'uuid',
                Rule::exists('ticket_types', 'uuid')->where(function ($query) {
                    $query->where('tenant_id', app('tenant_id'))->whereNull('deleted_at');
                }),
            ],
            'discount_type' => ['required', 'string', Rule::in(['fixed_price', 'percentage'])],
            // required_if: cada tipo exige só o campo correspondente —
            // promo_price é o valor "de/por" absoluto (fixed_price),
            // discount_percentage é o % sobre o TicketType.price vigente
            // (percentage). Ver ProductPromotion::effectivePrice().
            'promo_price' => ['required_if:discount_type,fixed_price', 'nullable', 'numeric', 'min:0'],
            'discount_percentage' => ['required_if:discount_type,percentage', 'nullable', 'numeric', 'min:0.01', 'max:100'],
            'starts_at' => ['nullable', 'date'],
            'expires_at' => ['nullable', 'date'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($this->input('discount_type') === 'percentage') {
                return;
            }

            // fixed_price nunca aceita discount_percentage preenchido —
            // evita estado ambíguo (os dois campos setados ao mesmo tempo).
            if ($this->filled('discount_percentage')) {
                $validator->errors()->add('discount_percentage', __('messages.product_promotion.discount_percentage_not_allowed'));
            }
        });
    }
}
