<?php

namespace App\Http\Requests\Restaurant;

use App\Enums\PriceLevel;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRestaurantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'email' => ['sometimes', 'nullable', 'email', 'max:255'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:32'],
            'street' => ['sometimes', 'nullable', 'string', 'max:255'],
            'postal_code' => ['sometimes', 'nullable', 'string', 'max:16'],
            'city' => ['sometimes', 'nullable', 'string', 'max:120'],
            'latitude' => ['sometimes', 'nullable', 'numeric', 'between:-90,90', 'required_with:longitude'],
            'longitude' => ['sometimes', 'nullable', 'numeric', 'between:-180,180', 'required_with:latitude'],
            'price_level' => ['sometimes', 'nullable', Rule::enum(PriceLevel::class)],
            'accepts_preorders' => ['sometimes', 'boolean'],
            'accepts_online_payment' => ['sometimes', 'boolean'],
            'cancellation_deadline_hours' => ['sometimes', 'integer', 'min:0', 'max:65535'],
            'booking_horizon_days' => ['sometimes', 'integer', 'min:1', 'max:365'],
            'cuisine_type_ids' => ['sometimes', 'array'],
            'cuisine_type_ids.*' => ['integer', Rule::exists('cuisine_types', 'id')->where('is_active', true)],
        ];
    }
}
