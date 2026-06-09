<?php

namespace App\Http\Requests\Service;

use App\Enums\ServiceType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreServiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        // L'appartenance est garantie par le middleware can:update,restaurant de la route.
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'type' => ['required', Rule::enum(ServiceType::class)],
            'capacity_pool_key' => ['nullable', 'string', 'max:40'],
            'max_simultaneous_covers' => ['required', 'integer', 'min:1', 'max:65535'],
            'max_covers_per_slot' => ['required', 'integer', 'min:1', 'max:65535', 'lte:max_simultaneous_covers'],
            'seating_duration_minutes' => ['nullable', 'integer', 'min:1', 'max:1440'],
            'slot_interval_minutes' => ['nullable', 'integer', 'min:1', 'max:255'],
            'min_party_size' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'max_party_size' => ['nullable', 'integer', 'min:1', 'max:65535', 'gte:min_party_size'],
            'position' => ['nullable', 'integer', 'min:0', 'max:65535'],
            'is_active' => ['boolean'],
        ];
    }
}
