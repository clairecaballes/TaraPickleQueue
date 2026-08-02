<?php

namespace App\Models;

use App\Enums\QueueStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QueueEntry extends Model
{
    protected $fillable = [
        'court_id',
        'user_id',
        'group_id',
        'players_count',
        'position',
        'status',
        'joined_at',
        'called_at',
        'resolved_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => QueueStatus::class,
            'joined_at' => 'datetime',
            'called_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    public function court(): BelongsTo
    {
        return $this->belongsTo(Court::class);
    }

    /** Solo entrant (null when a group joined instead). */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Party entrant (null when a solo player joined instead). */
    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }
}
