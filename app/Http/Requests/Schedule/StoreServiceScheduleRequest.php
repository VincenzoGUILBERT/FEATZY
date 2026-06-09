<?php

namespace App\Http\Requests\Schedule;

use App\Enums\DayOfWeek;
use App\Models\Service;
use App\Support\Availability\ScheduleWindowRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreServiceScheduleRequest extends FormRequest
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
            'day_of_week' => ['required', Rule::enum(DayOfWeek::class)],
            'opens_at' => ['required', 'date_format:H:i,H:i:s'],
            'last_seating_at' => ['required', 'date_format:H:i,H:i:s'],
            'closes_at' => ['required', 'date_format:H:i,H:i:s'],
            'crosses_midnight' => ['boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            /** @var Service $service */
            $service = $this->route('service');

            ScheduleWindowRules::validate(
                $validator,
                $service,
                $this->normalize($this->input('opens_at')),
                $this->normalize($this->input('last_seating_at')),
                $this->normalize($this->input('closes_at')),
                $this->boolean('crosses_midnight'),
            );
        });
    }

    private function normalize(?string $time): string
    {
        return str_pad((string) $time, 8, ':00');
    }
}
