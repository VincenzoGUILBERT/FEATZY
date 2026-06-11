<?php

namespace App\Http\Requests\Account;

use Illuminate\Foundation\Http\FormRequest;

class UpdateNotificationPreferencesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'email' => ['sometimes', 'boolean'],
            'push' => ['sometimes', 'boolean'],
            'important_updates' => ['sometimes', 'boolean'],
            'promotions' => ['sometimes', 'boolean'],
        ];
    }
}
