<?php

namespace App\Http\Requests\Review;

use App\Enums\ReservationStatus;
use App\Models\Reservation;
use App\Models\Restaurant;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreReviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        // The role:client middleware on the route guards who may review; the
        // attended-reservation constraint is enforced through the rules below.
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
            'reservation_id' => [
                'required',
                'integer',
                // Must be a completed reservation of this very restaurant…
                Rule::exists('reservations', 'id')
                    ->where('restaurant_id', $restaurant->id)
                    ->where('status', ReservationStatus::Completed->value),
                // …not already reviewed by this user. The DB UNIQUE counts
                // soft-deleted rows too, so this matches it (no deleted_at filter).
                Rule::unique('reviews', 'reservation_id')
                    ->where('user_id', $this->user()->id),
            ],
            'rating' => ['required', 'integer', 'between:1,5'],
            'comment' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * The reviewer must have taken part in the reservation (organizer or guest).
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $reservation = Reservation::query()->find($this->integer('reservation_id'));

            if ($reservation === null) {
                return;
            }

            $attended = $reservation->organizer_id === $this->user()->id
                || $reservation->participants()->where('user_id', $this->user()->id)->exists();

            if (! $attended) {
                $validator->errors()->add('reservation_id', 'You can only review a reservation you attended.');
            }
        });
    }
}
