<?php

namespace App\Http\Requests\Service;

use App\Enums\ServiceType;
use App\Models\Service;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateServiceRequest extends FormRequest
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
            'name' => ['sometimes', 'string', 'max:120'],
            'type' => ['sometimes', Rule::enum(ServiceType::class)],
            'capacity_pool_key' => ['sometimes', 'nullable', 'string', 'max:40'],
            'max_simultaneous_covers' => ['sometimes', 'integer', 'min:1', 'max:65535'],
            'max_covers_per_slot' => ['sometimes', 'integer', 'min:1', 'max:65535'],
            'seating_duration_minutes' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:1440'],
            'slot_interval_minutes' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:255'],
            'min_party_size' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:65535'],
            'max_party_size' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:65535'],
            'position' => ['sometimes', 'integer', 'min:0', 'max:65535'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * Invariants croisés évalués avec repli sur les valeurs actuelles du service (PATCH partiel).
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            /** @var Service $service */
            $service = $this->route('service');

            $maxSimultaneous = $this->has('max_simultaneous_covers')
                ? $this->integer('max_simultaneous_covers')
                : $service->max_simultaneous_covers;
            $maxPerSlot = $this->has('max_covers_per_slot')
                ? $this->integer('max_covers_per_slot')
                : $service->max_covers_per_slot;

            if ($maxPerSlot > $maxSimultaneous) {
                $validator->errors()->add('max_covers_per_slot', 'Le pacing ne peut pas dépasser le nombre de couverts simultanés.');
            }

            $min = $this->has('min_party_size') ? $this->input('min_party_size') : $service->min_party_size;
            $max = $this->has('max_party_size') ? $this->input('max_party_size') : $service->max_party_size;

            if ($min !== null && $max !== null && (int) $max < (int) $min) {
                $validator->errors()->add('max_party_size', 'La taille maximale doit être supérieure ou égale à la taille minimale.');
            }
        });
    }
}
