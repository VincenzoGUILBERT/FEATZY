<?php

namespace App\Http\Requests\Schedule;

use App\Enums\ScheduleExceptionType;
use App\Models\Restaurant;
use App\Models\ScheduleException;
use App\Support\Availability\ScheduleExceptionRules;
use Illuminate\Contracts\Validation\ValidationRule;
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
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var Restaurant $restaurant */
        $restaurant = $this->route('restaurant');

        $requiredForSpecialHours = Rule::requiredIf(
            fn (): bool => $this->input('type') === ScheduleExceptionType::SpecialHours->value,
        );

        return [
            'service_id' => [
                'nullable',
                'integer',
                Rule::exists('services', 'id')->where('restaurant_id', $restaurant->id),
            ],
            'date' => ['required', 'date_format:Y-m-d'],
            'type' => ['required', Rule::enum(ScheduleExceptionType::class)],
            'opens_at' => [$requiredForSpecialHours, 'nullable', 'date_format:H:i,H:i:s'],
            'last_seating_at' => [$requiredForSpecialHours, 'nullable', 'date_format:H:i,H:i:s'],
            'closes_at' => [$requiredForSpecialHours, 'nullable', 'date_format:H:i,H:i:s'],
            'crosses_midnight' => ['boolean'],
            'capacity_override' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'pacing_override' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'reason' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            /** @var Restaurant $restaurant */
            $restaurant = $this->route('restaurant');
            $type = $this->input('type');

            ScheduleExceptionRules::validate(
                $validator,
                $type,
                $this->boolean('crosses_midnight'),
                $this->input('opens_at'),
                $this->input('last_seating_at'),
                $this->input('closes_at'),
                $this->has('capacity_override') ? $this->integer('capacity_override') : null,
                $this->has('pacing_override') ? $this->integer('pacing_override') : null,
            );

            $serviceId = $this->input('service_id');

            $exists = ScheduleException::query()
                ->where('restaurant_id', $restaurant->id)
                ->where('date', $this->input('date'))
                ->where('type', $type)
                ->when(
                    $serviceId === null,
                    fn ($query) => $query->whereNull('service_id'),
                    fn ($query) => $query->where('service_id', $serviceId),
                )
                ->exists();

            if ($exists) {
                $validator->errors()->add('type', 'Une dérogation de ce type existe déjà pour cette date et ce service.');
            }
        });
    }
}
