<?php

namespace App\Http\Requests\FriendGroup;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class SyncFriendGroupMembersRequest extends FormRequest
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
            'members' => ['present', 'array'],
            'members.*' => ['integer', 'distinct', 'exists:users,id'],
        ];
    }
}
