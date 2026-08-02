<?php

namespace App\Enums;

enum CourtRotationRule: string
{
    /** Winning team stays on court; losing team requeues at the back. */
    case WinnersStay = 'winners_stay';

    /** All players rotate off; the whole court requeues at the back. */
    case FourOnFourOff = 'four_on_four_off';

    /** Winning team stays on court; losing team is removed from the queue. */
    case LosersOut = 'losers_out';
}
