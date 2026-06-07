<?php

namespace App\Http\Requests\FriendGroup;

use App\Models\FriendGroup;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateFriendGroupRequest extends FormRequest
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
        /** @var FriendGroup $friendGroup */
        $friendGroup = $this->route('friendGroup');

        return [
            'name' => [
                'sometimes',
                'required',
                'string',
                'max:100',
                // The raw unique query counts trashed rows (no soft-delete scope),
                // matching the DB UNIQUE(owner_id, name) which ignores deleted_at.
                Rule::unique('friend_groups')
                    ->where('owner_id', $friendGroup->owner_id)
                    ->ignore($friendGroup->id),
            ],
        ];
    }
}
