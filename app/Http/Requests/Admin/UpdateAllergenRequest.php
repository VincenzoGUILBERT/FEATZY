<?php

namespace App\Http\Requests\Admin;

use App\Models\Allergen;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAllergenRequest extends FormRequest
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
        /** @var Allergen $allergen */
        $allergen = $this->route('allergen');

        return [
            'name' => ['sometimes', 'required', 'string', 'max:255', Rule::unique('allergens', 'name')->ignore($allergen->id)],
            'icon' => ['sometimes', 'nullable', 'string', 'max:255'],
            'position' => ['sometimes', 'integer', 'min:0', 'max:65535'],
        ];
    }
}
