<?php

namespace App\Http\Requests\Account;

use App\Enums\DietaryPreference;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDietaryPreferencesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'dietary_preferences' => ['present', 'array'],
            'dietary_preferences.*' => ['distinct', Rule::enum(DietaryPreference::class)],
        ];
    }
}
