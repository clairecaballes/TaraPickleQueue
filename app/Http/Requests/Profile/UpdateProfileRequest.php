<?php

namespace App\Http\Requests\Profile;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * "sometimes" allows partial updates — only the fields the client sends
     * are validated.
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            // Mirrors the DB CHECK (1.0 - 5.0); decimal:0,1 = at most one decimal place.
            'skill_rating' => ['sometimes', 'nullable', 'numeric', 'min:1', 'max:5', 'decimal:0,1'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:32'],
            // Avatar is stored as a URL/path string (users.avatar is string(2048)).
            'avatar' => ['sometimes', 'nullable', 'url', 'max:2048'],
        ];
    }
}
