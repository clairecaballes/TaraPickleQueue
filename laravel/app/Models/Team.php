<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Team extends Model
{
    use HasFactory;

    protected $fillable = [
        'match_id',
        'score',
    ];

    protected function casts(): array
    {
        return [
            'score' => 'integer',
        ];
    }

    public function match(): BelongsTo
    {
        return $this->belongsTo(Game::class, 'match_id');
    }

    /** 1 player (singles) or 2 (doubles). */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class);
    }
}
