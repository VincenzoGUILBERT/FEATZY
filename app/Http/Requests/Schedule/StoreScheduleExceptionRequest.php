<?php

namespace App\Http\Requests\Schedule;

use App\Enums\ServiceType;
use App\Models\Restaurant;
use App\Models\ScheduleException;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreScheduleExceptionRequest extends FormRequest
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
            'date' => ['required', 'date_format:Y-m-d'],
            'service_type' => ['nullable', Rule::enum(ServiceType::class)],
            'is_closed' => ['boolean'],
            'capacity' => ['nullable', 'integer', 'min:0', 'max:65535'],
            'max_party_size' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'start_time' => ['nullable', 'date_format:H:i,H:i:s'],
            'end_time' => ['nullable', 'date_format:H:i,H:i:s'],
            'reason' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            /** @var Restaurant $restaurant */
            $restaurant = $this->route('restaurant');

            $start = $this->input('start_time');
            $end = $this->input('end_time');
            if ($start !== null && $end !== null && strtotime((string) $end) <= strtotime((string) $start)) {
                $validator->errors()->add('end_time', 'The end time must be after the start time.');
            }

            $capacity = $this->input('capacity');
            $maxParty = $this->input('max_party_size');
            if ($capacity !== null && $maxParty !== null && (int) $maxParty > (int) $capacity) {
                $validator->errors()->add('max_party_size', 'The max party size must not exceed the capacity.');
            }

            $serviceType = $this->input('service_type');
            $query = ScheduleException::query()
                ->where('restaurant_id', $restaurant->id)
                ->where('date', $this->input('date'));
            $query = $serviceType === null
                ? $query->whereNull('service_type')
                : $query->where('service_type', $serviceType);

            if ($query->exists()) {
                $validator->errors()->add('date', 'An exception already exists for this date and service.');
            }
        });
    }
}
