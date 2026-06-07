<?php

namespace App\Http\Requests\Schedule;

use App\Enums\DayOfWeek;
use App\Enums\ServiceType;
use App\Models\ServiceSchedule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateServiceScheduleRequest extends FormRequest
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
            'day_of_week' => ['sometimes', 'required', Rule::enum(DayOfWeek::class)],
            'service_type' => ['sometimes', 'required', Rule::enum(ServiceType::class)],
            'start_time' => ['sometimes', 'required', 'date_format:H:i,H:i:s'],
            'end_time' => ['sometimes', 'required', 'date_format:H:i,H:i:s'],
            'capacity' => ['sometimes', 'required', 'integer', 'min:0', 'max:65535'],
            'max_party_size' => ['sometimes', 'required', 'integer', 'min:1', 'max:65535'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            /** @var ServiceSchedule $schedule */
            $schedule = $this->route('serviceSchedule');

            $day = $this->has('day_of_week') ? (int) $this->input('day_of_week') : $schedule->day_of_week->value;
            $type = $this->has('service_type') ? $this->input('service_type') : $schedule->service_type->value;
            $start = $this->has('start_time') ? $this->input('start_time') : $schedule->start_time;
            $end = $this->has('end_time') ? $this->input('end_time') : $schedule->end_time;
            $capacity = $this->has('capacity') ? (int) $this->input('capacity') : $schedule->capacity;
            $maxParty = $this->has('max_party_size') ? (int) $this->input('max_party_size') : $schedule->max_party_size;

            if ($start !== null && $end !== null && strtotime((string) $end) <= strtotime((string) $start)) {
                $validator->errors()->add('end_time', 'The end time must be after the start time.');
            }

            if ($maxParty > $capacity) {
                $validator->errors()->add('max_party_size', 'The max party size must not exceed the capacity.');
            }

            $conflict = ServiceSchedule::query()
                ->where('restaurant_id', $schedule->restaurant_id)
                ->where('day_of_week', $day)
                ->where('service_type', $type)
                ->whereKeyNot($schedule->id)
                ->exists();

            if ($conflict) {
                $validator->errors()->add('service_type', 'A schedule already exists for this day and service.');
            }
        });
    }
}
