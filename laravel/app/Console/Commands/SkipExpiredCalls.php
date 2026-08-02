<?php

namespace App\Console\Commands;

use App\Services\QueueService;
use Illuminate\Console\Command;

class SkipExpiredCalls extends Command
{
    protected $signature = 'queue:skip-expired-calls';

    protected $description = 'Skip queue entries that did not confirm their court call within 2 minutes';

    public function handle(QueueService $queue): int
    {
        $count = $queue->skipExpiredCalls();

        $this->info("Skipped {$count} expired call(s).");

        return self::SUCCESS;
    }
}
