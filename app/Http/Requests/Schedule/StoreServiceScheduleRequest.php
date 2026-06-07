<?php

namespace App\Http\Requests\Schedule;

use App\Enums\DayOfWeek;
use App\Enums\ServiceType;
use App\Models\Restaurant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreServiceScheduleRequest extends FormRequest
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
        /** @var Restaurant $restaurant */
        $restaurant = $this->route('restaurant');

        return [
            'day_of_week' => ['required', Rule::enum(DayOfWeek::class)],
            'service_type' => [
                'required',
                Rule::enum(ServiceType::class),
                Rule::unique('service_schedules')
                    ->where('restaurant_id', $restaurant->id)
                    ->where('day_of_week', (int) $this->input('day_of_week')),
            ],
            'start_time' => ['required', 'date_format:H:i,H:i:s'],
            'end_time' => ['required', 'date_format:H:i,H:i:s', 'after:start_time'],
            'capacity' => ['required', 'integer', 'min:0', 'max:65535'],
            'max_party_size' => ['required', 'integer', 'min:1', 'max:65535', 'lte:capacity'],
            'is_active' => ['boolean'],
        ];
    }
}
