<?php

namespace App\Http\Requests\Queue;

use Illuminate\Foundation\Http\FormRequest;

class JoinQueueRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * No group_id means the authenticated user joins solo.
     * Membership and group-size rules are enforced by the QueueService.
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'group_id' => ['nullable', 'integer', 'exists:groups,id'],
        ];
    }
}
