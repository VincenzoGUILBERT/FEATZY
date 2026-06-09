<?php

namespace App\Http\Requests\Reservation;

use App\Models\Restaurant;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreReservationRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Le middleware role:client de la route garde qui peut réserver ; la capacité et
        // la validité du créneau sont vérifiées dans CreateReservationAction.
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
            'service_id' => [
                'required',
                'integer',
                Rule::exists('services', 'id')
                    ->where('restaurant_id', $restaurant->id)
                    ->where('is_active', true),
            ],
            'date' => ['required', 'date_format:Y-m-d'],
            'reserved_at' => ['required', 'date_format:Y-m-d H:i:s,Y-m-d\TH:i:s'],
            'party_size' => ['required', 'integer', 'min:1', 'max:65535'],
            'is_preorder' => ['sometimes', 'boolean'],
            'special_requests' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
