<?php

namespace App\Http\Requests\Discovery;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class IndexRestaurantsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'latitude' => ['nullable', 'required_with:longitude', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'required_with:latitude', 'numeric', 'between:-180,180'],
            'radius' => ['nullable', 'numeric', 'gt:0', 'max:20000'],
            'filter.search' => ['nullable', 'string', 'max:255'],
            'filter.city' => ['nullable', 'string', 'max:255'],
            'filter.cuisine' => ['nullable', 'integer', 'exists:cuisine_types,id'],
            'filter.accepts_preorders' => ['nullable', 'boolean'],
            'filter.min_rating' => ['nullable', 'numeric', 'between:0,5'],
            'filter.price_level' => ['nullable', 'integer', 'between:1,3'],
        ];
    }
}
