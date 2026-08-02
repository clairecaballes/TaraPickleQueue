<?php

namespace App\Console\Commands;

use App\Models\VisitorSession;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CloseStaleSessions extends Command
{
    protected $signature = 'visitor-sessions:close-stale';

    protected $description = 'Close visitor sessions that have been idle for over 30 minutes';

    public function handle(): int
    {
        $threshold = now()->subMinutes(VisitorSession::TIMEOUT_MINUTES);

        $closed = VisitorSession::whereNull('ended_at')
            ->where('last_activity_at', '<', $threshold)
            ->update([
                'ended_at' => DB::raw('last_activity_at'),
            ]);

        // Retention: raw IPs / user agents are personal data — purge sessions
        // older than a year so the analytics never hoard stale data forever.
        $purged = VisitorSession::where('started_at', '<', now()->subYear())->delete();

        $this->info("Closed {$closed} stale session(s); purged {$purged} older than a year.");

        return self::SUCCESS;
    }
}
