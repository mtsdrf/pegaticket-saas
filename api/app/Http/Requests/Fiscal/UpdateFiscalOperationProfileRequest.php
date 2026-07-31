<?php

namespace App\Http\Requests\Fiscal;

class UpdateFiscalOperationProfileRequest extends StoreFiscalOperationProfileRequest
{
    public function rules(): array
    {
        $rules = parent::rules();

        foreach ($rules as $field => $rule) {
            if ($field === 'scope' || str_starts_with($field, 'scope.')) {
                $rules[$field] = array_merge(['sometimes'], $rule);
                continue;
            }

            $rules[$field] = array_merge(['sometimes'], array_values(array_filter(
                $rule,
                fn ($item) => $item !== 'required'
            )));
        }

        return $rules;
    }
}
