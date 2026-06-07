<?php

namespace App\Http\Requests\Schedule;

use App\Enums\ServiceType;
use App\Models\ScheduleException;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateScheduleExceptionRequest extends FormRequest
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
            'date' => ['sometimes', 'required', 'date_format:Y-m-d'],
            'service_type' => ['sometimes', 'nullable', Rule::enum(ServiceType::class)],
            'is_closed' => ['sometimes', 'boolean'],
            'capacity' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:65535'],
            'max_party_size' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:65535'],
            'start_time' => ['sometimes', 'nullable', 'date_format:H:i,H:i:s'],
            'end_time' => ['sometimes', 'nullable', 'date_format:H:i,H:i:s'],
            'reason' => ['sometimes', 'nullable', 'string', 'max:255'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            /** @var ScheduleException $exception */
            $exception = $this->route('scheduleException');

            $date = $this->has('date') ? $this->input('date') : $exception->date?->toDateString();
            $serviceType = $this->has('service_type') ? $this->input('service_type') : $exception->service_type?->value;
            $start = $this->has('start_time') ? $this->input('start_time') : $exception->start_time;
            $end = $this->has('end_time') ? $this->input('end_time') : $exception->end_time;

            if ($start !== null && $end !== null && strtotime((string) $end) <= strtotime((string) $start)) {
                $validator->errors()->add('end_time', 'The end time must be after the start time.');
            }

            $capacity = $this->has('capacity') ? $this->input('capacity') : $exception->capacity;
            $maxParty = $this->has('max_party_size') ? $this->input('max_party_size') : $exception->max_party_size;
            if ($capacity !== null && $maxParty !== null && (int) $maxParty > (int) $capacity) {
                $validator->errors()->add('max_party_size', 'The max party size must not exceed the capacity.');
            }

            $query = ScheduleException::query()
                ->where('restaurant_id', $exception->restaurant_id)
                ->where('date', $date)
                ->whereKeyNot($exception->id);
            $query = $serviceType === null
                ? $query->whereNull('service_type')
                : $query->where('service_type', $serviceType);

            if ($query->exists()) {
                $validator->errors()->add('date', 'An exception already exists for this date and service.');
            }
        });
    }
}
