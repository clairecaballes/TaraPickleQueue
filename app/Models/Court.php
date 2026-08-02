<?php

namespace App\Models;

use App\Enums\CourtPlayType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Court extends Model
{
    protected $fillable = [
        'name',
        'location',
        'play_type',
        'max_players',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'play_type' => CourtPlayType::class,
            'is_active' => 'boolean',
            'max_players' => 'integer',
        ];
    }

    /** Everyone currently in line for this court. */
    public function queueEntries(): HasMany
    {
        return $this->hasMany(QueueEntry::class);
    }

    /** Match history played on this court. */
    public function matches(): HasMany
    {
        return $this->hasMany(Game::class);
    }
}
