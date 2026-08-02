<?php

namespace App\Enums;

enum QueueStatus: string
{
    case Waiting = 'waiting';
    case OnCourt = 'on_court';
    case Completed = 'completed';
    case Skipped = 'skipped';
}
