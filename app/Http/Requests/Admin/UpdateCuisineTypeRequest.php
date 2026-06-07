<?php

namespace App\Http\Requests\Admin;

use App\Models\CuisineType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCuisineTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Access is gated by the role:admin route middleware.
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var CuisineType $cuisineType */
        $cuisineType = $this->route('cuisineType');

        return [
            'name' => ['sometimes', 'required', 'string', 'max:120', Rule::unique('cuisine_types', 'name')->ignore($cuisineType->id)],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
