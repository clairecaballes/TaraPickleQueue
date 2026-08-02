<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Court rotation rules decide what happens to the two teams after a match:
     *  - winners_stay:    winning team requeues at the front, losing team at the back
     *  - four_on_four_off: everyone requeues at the back (fixed rotation)
     *  - losers_out:       winning team requeues at the front, losing team leaves the queue
     *
     * skill_grouping sorts the waiting line by skill bucket (0.5) instead of pure FIFO
     * so the next 4 players called are of similar rating.
     */
    public function up(): void
    {
        Schema::table('courts', function (Blueprint $table) {
            $table->enum('rotation_rule', ['winners_stay', 'four_on_four_off', 'losers_out'])
                ->default('winners_stay')
                ->after('is_active');
            $table->boolean('skill_grouping')->default(false)->after('rotation_rule');

            $table->index('rotation_rule');
        });
    }

    public function down(): void
    {
        Schema::table('courts', function (Blueprint $table) {
            $table->dropIndex(['rotation_rule']);
            $table->dropColumn(['rotation_rule', 'skill_grouping']);
        });
    }
};
