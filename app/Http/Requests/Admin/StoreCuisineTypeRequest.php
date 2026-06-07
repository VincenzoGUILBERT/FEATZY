<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCuisineTypeRequest extends FormRequest
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
        return [
            'name' => ['required', 'string', 'max:120', Rule::unique('cuisine_types', 'name')],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
