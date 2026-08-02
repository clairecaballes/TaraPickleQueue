<?php

namespace App\Models;

use App\Enums\MatchStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A pickleball match (table: matches).
 *
 * Note: the model is named "Game" because "Match" is a reserved keyword in
 * PHP 8 (verified: `class Match {}` is a parse error), so `Match` cannot be
 * used as a class name. The underlying database table stays `matches`.
 */
class Game extends Model
{
    use HasFactory;

    protected $table = 'matches';

    protected $fillable = [
        'court_id',
        'status',
        'winner_team_id',
        'started_at',
        'ended_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => MatchStatus::class,
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
        ];
    }

    public function court(): BelongsTo
    {
        return $this->belongsTo(Court::class);
    }

    /** Both sides of the match (exactly two rows). */
    public function teams(): HasMany
    {
        return $this->hasMany(Team::class, 'match_id');
    }

    public function winnerTeam(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'winner_team_id');
    }

    /** The non-winning team; null until a winner is recorded. */
    public function loserTeam(): ?Team
    {
        if ($this->winner_team_id === null) {
            return null;
        }

        return $this->teams()->whereKeyNot($this->winner_team_id)->first();
    }
}
