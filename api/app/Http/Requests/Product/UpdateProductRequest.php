<?php

namespace App\Http\Requests\Product;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'price' => ['sometimes', 'numeric', 'min:0'],
            'description' => ['nullable', 'string'],
            'is_available' => ['sometimes', 'boolean'],
            'surcharge_rate' => ['nullable', 'numeric', 'min:0'],
            'image' => ['nullable', 'image', 'max:5120'],
            'product_type_uuid' => [
                'sometimes',
                'uuid',
                Rule::exists('product_types', 'uuid')->where(function ($query) {
                    $query->where('tenant_id', app('tenant_id'))
                        ->whereNull('deleted_at');
                }),
            ],
            'sku' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('products', 'sku')
                    ->where(function ($query) {
                        $query->where('tenant_id', app('tenant_id'))
                            ->whereNull('deleted_at');
                    })
                    ->ignore($this->route('product')?->id),
            ],
            'barcode' => ['nullable', 'string', 'max:255'],
            'brand' => ['nullable', 'string', 'max:255'],
            'ncm' => ['nullable', 'string', 'max:10'],
            'cest' => ['nullable', 'string', 'max:10'],
            'origin' => ['nullable', 'string', 'max:4'],
            'default_cfop' => ['nullable', 'string', 'max:10'],
            'csosn_cst' => ['nullable', 'string', 'max:10'],
            'unit' => ['nullable', 'string', 'max:50'],
            'is_lot_controlled' => ['sometimes', 'boolean'],
            'is_expiry_controlled' => ['sometimes', 'boolean'],
            'is_serial_controlled' => ['sometimes', 'boolean'],
            'min_stock' => ['nullable', 'numeric', 'min:0'],
            'max_stock' => ['nullable', 'numeric', 'min:0'],
            'reorder_point' => ['nullable', 'numeric', 'min:0'],
            'reorder_qty' => ['nullable', 'numeric', 'min:0'],
            'last_purchase_cost' => ['nullable', 'numeric', 'min:0'],
            'option_groups' => ['sometimes', 'array'],
            'option_groups.*.uuid' => ['nullable', 'uuid'],
            'option_groups.*.name' => ['required_with:option_groups', 'string', 'max:255'],
            'option_groups.*.description' => ['nullable', 'string'],
            'option_groups.*.kind' => ['nullable', 'string', Rule::in(['addon', 'ingredient_removal'])],
            'option_groups.*.min_select' => ['required_with:option_groups', 'integer', 'min:0'],
            'option_groups.*.max_select' => ['required_with:option_groups', 'integer', 'min:0'],
            'option_groups.*.sort_order' => ['nullable', 'integer', 'min:0'],
            'option_groups.*.is_active' => ['boolean'],
            'option_groups.*.options' => ['required_with:option_groups', 'array', 'min:1'],
            'option_groups.*.options.*.uuid' => ['nullable', 'uuid'],
            'option_groups.*.options.*.name' => ['required_with:option_groups.*.options', 'string', 'max:255'],
            'option_groups.*.options.*.description' => ['nullable', 'string'],
            'option_groups.*.options.*.price' => ['required_with:option_groups.*.options', 'numeric', 'min:0'],
            'option_groups.*.options.*.sort_order' => ['nullable', 'integer', 'min:0'],
            'option_groups.*.options.*.is_available' => ['boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $groups = collect($this->input('option_groups', []));

            $duplicateGroupNames = $groups
                ->map(fn (mixed $group) => is_array($group) ? mb_strtolower(trim((string) ($group['name'] ?? ''))) : '')
                ->filter()
                ->duplicates();

            foreach ($groups as $groupIndex => $group) {
                if (!is_array($group)) {
                    continue;
                }

                $min = (int) ($group['min_select'] ?? 0);
                $max = (int) ($group['max_select'] ?? 0);

                if ($max < $min) {
                    $validator->errors()->add("option_groups.$groupIndex.max_select", __('messages.product.option_group_invalid_limits'));
                }

                $normalizedName = mb_strtolower(trim((string) ($group['name'] ?? '')));
                if ($normalizedName !== '' && $duplicateGroupNames->contains($normalizedName)) {
                    $validator->errors()->add("option_groups.$groupIndex.name", __('messages.product.option_group_duplicate_name'));
                }

                $optionNames = collect($group['options'] ?? [])
                    ->map(fn (mixed $option) => is_array($option) ? mb_strtolower(trim((string) ($option['name'] ?? ''))) : '')
                    ->filter();

                foreach ($optionNames->duplicates() as $duplicateName) {
                    foreach (($group['options'] ?? []) as $optionIndex => $option) {
                        if (
                            is_array($option)
                            && mb_strtolower(trim((string) ($option['name'] ?? ''))) === $duplicateName
                        ) {
                            $validator->errors()->add("option_groups.$groupIndex.options.$optionIndex.name", __('messages.product.option_duplicate_name'));
                        }
                    }
                }
            }
        });
    }

    public function messages(): array
    {
        return [
            'product_type_uuid.exists' => __('messages.product.invalid_type'),
            'sku.unique' => __('messages.product.sku_exists'),
        ];
    }
}
