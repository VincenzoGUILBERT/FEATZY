<?php

namespace App\Http\Requests\Reservation;

use App\Models\Restaurant;
use App\Models\ServiceAvailability;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreReservationRequest extends FormRequest
{
    public function authorize(): bool
    {
        // The role:client middleware on the route guards who may book; capacity
        // and ownership-free booking is open to any authenticated client.
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
            'service_availability_id' => [
                'required',
                'integer',
                Rule::exists('service_availabilities', 'id')->where('restaurant_id', $restaurant->id),
            ],
            'party_size' => ['required', 'integer', 'min:1'],
            'is_preorder' => ['sometimes', 'boolean'],
            'special_requests' => ['nullable', 'string', 'max:1000'],
            'expected_arrival_time' => ['nullable', 'date_format:H:i,H:i:s'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $slot = ServiceAvailability::query()->find($this->integer('service_availability_id'));

            if ($slot === null) {
                return;
            }

            if ($slot->date->lessThan(CarbonImmutable::today())) {
                $validator->errors()->add('service_availability_id', 'This service slot is in the past.');
            }

            $partySize = $this->integer('party_size');

            if ($slot->max_party_size !== null && $partySize > $slot->max_party_size) {
                $validator->errors()->add('party_size', "The party size exceeds the maximum of {$slot->max_party_size} for this slot.");
            }
        });
    }
}
