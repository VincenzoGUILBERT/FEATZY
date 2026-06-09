<?php

namespace App\Http\Requests\Availability;

use App\Models\Restaurant;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AvailabilityRequest extends FormRequest
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
        /** @var Restaurant $restaurant */
        $restaurant = $this->route('restaurant');

        return [
            'date' => ['required', 'date_format:Y-m-d'],
            'party_size' => ['required', 'integer', 'min:1', 'max:65535'],
            'service_id' => [
                'nullable',
                'integer',
                Rule::exists('services', 'id')
                    ->where('restaurant_id', $restaurant->id)
                    ->where('is_active', true),
            ],
        ];
    }
}
