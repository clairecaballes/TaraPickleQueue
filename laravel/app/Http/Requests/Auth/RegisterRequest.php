<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class RegisterRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            // Mirrors the DB CHECK (1.0 - 5.0); decimal:0,1 = at most one decimal place.
            'skill_rating' => ['nullable', 'numeric', 'min:1', 'max:5', 'decimal:0,1'],
            'phone' => ['nullable', 'string', 'max:32'],
        ];
    }
}
