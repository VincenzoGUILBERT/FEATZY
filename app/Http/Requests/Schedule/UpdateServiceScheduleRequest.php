<?php

namespace App\Http\Requests\Schedule;

use App\Enums\DayOfWeek;
use App\Models\ServiceSchedule;
use App\Support\Availability\ScheduleWindowRules;
use Illuminate\Contracts\Validation\ValidationRule;
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
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'day_of_week' => ['sometimes', Rule::enum(DayOfWeek::class)],
            'opens_at' => ['sometimes', 'date_format:H:i,H:i:s'],
            'last_seating_at' => ['sometimes', 'date_format:H:i,H:i:s'],
            'closes_at' => ['sometimes', 'date_format:H:i,H:i:s'],
            'crosses_midnight' => ['sometimes', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            /** @var ServiceSchedule $schedule */
            $schedule = $this->route('serviceSchedule');
            $schedule->loadMissing('service.restaurant');

            ScheduleWindowRules::validate(
                $validator,
                $schedule->service,
                $this->resolve('opens_at', $schedule->opens_at),
                $this->resolve('last_seating_at', $schedule->last_seating_at),
                $this->resolve('closes_at', $schedule->closes_at),
                $this->has('crosses_midnight') ? $this->boolean('crosses_midnight') : (bool) $schedule->crosses_midnight,
            );
        });
    }

    private function resolve(string $key, ?string $fallback): string
    {
        return str_pad((string) ($this->has($key) ? $this->input($key) : $fallback), 8, ':00');
    }
}
