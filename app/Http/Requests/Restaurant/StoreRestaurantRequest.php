<?php

namespace App\Http\Requests\Restaurant;

use App\Enums\PriceLevel;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRestaurantRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:32'],
            'street' => ['nullable', 'string', 'max:255'],
            'postal_code' => ['nullable', 'string', 'max:16'],
            'city' => ['nullable', 'string', 'max:120'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90', 'required_with:longitude'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180', 'required_with:latitude'],
            'price_level' => ['nullable', Rule::enum(PriceLevel::class)],
            'accepts_preorders' => ['boolean'],
            'accepts_online_payment' => ['boolean'],
            'cancellation_deadline_hours' => ['integer', 'min:0', 'max:65535'],
            'booking_horizon_days' => ['integer', 'min:1', 'max:365'],
            'cuisine_type_ids' => ['sometimes', 'array'],
            'cuisine_type_ids.*' => ['integer', Rule::exists('cuisine_types', 'id')->where('is_active', true)],
        ];
    }
}
