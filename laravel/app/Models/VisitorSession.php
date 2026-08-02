<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A tracked browser session for the admin visitor analytics dashboard.
 * One visitor (cookie) can have many sessions over time.
 */
class VisitorSession extends Model
{
    use HasFactory;

    /** A session is considered over after this much idle time. */
    public const TIMEOUT_MINUTES = 30;

    protected $fillable = [
        'visitor_id',
        'session_id',
        'ip',
        'user_agent',
        'country',
        'region',
        'city',
        'path',
        'hits',
        'started_at',
        'last_activity_at',
        'ended_at',
    ];

    protected function casts(): array
    {
        return [
            'hits' => 'integer',
            'started_at' => 'datetime',
            'last_activity_at' => 'datetime',
            'ended_at' => 'datetime',
        ];
    }
}
