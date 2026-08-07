<?php

namespace App\Http\Requests\Venue;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateSeatRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'sector_name' => ['nullable', 'string', 'max:255'],
            'label' => ['sometimes', 'string', 'max:255'],
            'kind' => ['sometimes', 'string', Rule::in(['mesa', 'assento', 'area', 'camarote'])],
            'capacity' => ['nullable', 'integer', 'min:1'],
            'pos_x' => ['nullable', 'numeric'],
            'pos_y' => ['nullable', 'numeric'],
            'width' => ['nullable', 'numeric', 'gt:0'],
            'height' => ['nullable', 'numeric', 'gt:0'],
            'geometry_points' => ['nullable', 'array'],
            'geometry_points.*.x' => ['required_with:geometry_points', 'numeric'],
            'geometry_points.*.y' => ['required_with:geometry_points', 'numeric'],
            'is_accessible' => ['boolean'],
            'status' => ['sometimes', 'string', Rule::in(['disponivel', 'bloqueado', 'indisponivel'])],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $seat = $this->route('seat');
            $kind = $this->input('kind', $seat?->kind ?? 'assento');
            $geometryPoints = $this->input('geometry_points', $seat?->geometry_points);

            if ($kind !== 'area') {
                return;
            }

            if (!is_array($geometryPoints) || count($geometryPoints) < 3) {
                $validator->errors()->add('geometry_points', 'O campo geometry points deve ter no mínimo 3 itens.');
            }
        });
    }
}
