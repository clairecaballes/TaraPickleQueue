<?php

namespace App\Http\Requests\Queue;

use Illuminate\Foundation\Http\FormRequest;

class ReorderQueueRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * ordered_ids must be every waiting entry id of the court, in the desired
     * order — the QueueService validates the exact set.
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'ordered_ids' => ['required', 'array', 'min:1'],
            'ordered_ids.*' => ['required', 'integer', 'distinct'],
        ];
    }
}
