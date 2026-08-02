<?php

namespace App\Enums;

enum MatchStatus: string
{
    case Ongoing = 'ongoing';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
}
