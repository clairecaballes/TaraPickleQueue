<?php

namespace App\Models;

use App\Enums\CourtPlayType;
use App\Enums\CourtRotationRule;
use App\Enums\MatchStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Court extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'location',
        'play_type',
        'max_players',
        'is_active',
        'rotation_rule',
        'skill_grouping',
    ];

    protected function casts(): array
    {
        return [
            'play_type' => CourtPlayType::class,
            'rotation_rule' => CourtRotationRule::class,
            'is_active' => 'boolean',
            'skill_grouping' => 'boolean',
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

    /** The match currently in progress on this court, if any. */
    public function currentMatch(): HasOne
    {
        return $this->hasOne(Game::class)
            ->where('status', MatchStatus::Ongoing)
            ->latestOfMany('started_at');
    }
}
