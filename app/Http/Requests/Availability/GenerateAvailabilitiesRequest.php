<?php

namespace App\Http\Requests\Availability;

use App\Models\Restaurant;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class GenerateAvailabilitiesRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Ownership is enforced by the route's can:update,restaurant middleware.
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var Restaurant $restaurant */
        $restaurant = $this->route('restaurant');
        $horizonEnd = CarbonImmutable::today()->addDays($restaurant->booking_horizon_days)->toDateString();

        return [
            'from' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:today'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:today', 'before_or_equal:'.$horizonEnd],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $from = $this->input('from');
            $to = $this->input('to');

            if ($from !== null && $to !== null && $from > $to) {
                $validator->errors()->add('to', 'The to date must be on or after the from date.');
            }
        });
    }
}
