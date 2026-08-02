<?php

namespace App\Http\Requests\Match;

use Illuminate\Foundation\Http\FormRequest;

class CompleteMatchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * score_a / score_b map to Team A / Team B (team creation order).
     * The "no ties" rule is enforced by the QueueService.
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'score_a' => ['required', 'integer', 'min:0'],
            'score_b' => ['required', 'integer', 'min:0'],
        ];
    }
}
