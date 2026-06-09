<?php

namespace App\Http\Requests\Schedule;

use App\Enums\ScheduleExceptionType;
use App\Models\ScheduleException;
use App\Support\Availability\ScheduleExceptionRules;
use Illuminate\Contracts\Validation\ValidationRule;
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
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var ScheduleException $exception */
        $exception = $this->route('scheduleException');

        return [
            'service_id' => [
                'sometimes',
                'nullable',
                'integer',
                Rule::exists('services', 'id')->where('restaurant_id', $exception->restaurant_id),
            ],
            'date' => ['sometimes', 'date_format:Y-m-d'],
            'type' => ['sometimes', Rule::enum(ScheduleExceptionType::class)],
            'opens_at' => ['sometimes', 'nullable', 'date_format:H:i,H:i:s'],
            'last_seating_at' => ['sometimes', 'nullable', 'date_format:H:i,H:i:s'],
            'closes_at' => ['sometimes', 'nullable', 'date_format:H:i,H:i:s'],
            'crosses_midnight' => ['sometimes', 'boolean'],
            'capacity_override' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:65535'],
            'pacing_override' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:65535'],
            'reason' => ['sometimes', 'nullable', 'string', 'max:255'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            /** @var ScheduleException $exception */
            $exception = $this->route('scheduleException');

            $type = $this->has('type') ? $this->input('type') : $exception->type->value;
            $serviceId = $this->has('service_id') ? $this->input('service_id') : $exception->service_id;
            $date = $this->has('date') ? $this->input('date') : $exception->date?->toDateString();

            ScheduleExceptionRules::validate(
                $validator,
                $type,
                $this->has('crosses_midnight') ? $this->boolean('crosses_midnight') : (bool) $exception->crosses_midnight,
                $this->has('opens_at') ? $this->input('opens_at') : $exception->opens_at,
                $this->has('last_seating_at') ? $this->input('last_seating_at') : $exception->last_seating_at,
                $this->has('closes_at') ? $this->input('closes_at') : $exception->closes_at,
                $this->resolveOverride('capacity_override', $exception->capacity_override),
                $this->resolveOverride('pacing_override', $exception->pacing_override),
            );

            $exists = ScheduleException::query()
                ->where('restaurant_id', $exception->restaurant_id)
                ->where('date', $date)
                ->where('type', $type)
                ->when(
                    $serviceId === null,
                    fn ($query) => $query->whereNull('service_id'),
                    fn ($query) => $query->where('service_id', $serviceId),
                )
                ->whereKeyNot($exception->id)
                ->exists();

            if ($exists) {
                $validator->errors()->add('type', 'Une dérogation de ce type existe déjà pour cette date et ce service.');
            }
        });
    }

    private function resolveOverride(string $key, ?int $fallback): ?int
    {
        if (! $this->has($key)) {
            return $fallback;
        }

        return $this->input($key) === null ? null : $this->integer($key);
    }
}
