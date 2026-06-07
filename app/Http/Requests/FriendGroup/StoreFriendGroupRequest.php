<?php

namespace App\Http\Requests\FriendGroup;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreFriendGroupRequest extends FormRequest
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
            'name' => [
                'required',
                'string',
                'max:100',
                // The raw unique query has no soft-delete scope, so it already
                // counts trashed rows — matching the DB UNIQUE(owner_id, name)
                // (which ignores deleted_at) and 422-ing on a reused trashed name.
                Rule::unique('friend_groups')->where('owner_id', $this->user()->id),
            ],
        ];
    }
}
