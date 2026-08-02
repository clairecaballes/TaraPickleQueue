<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One row = one unit in line (a solo player OR a group) on one court.
     *
     * Lifecycle: waiting -> on_court -> completed | skipped
     */
    public function up(): void
    {
        Schema::create('queue_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('court_id')->constrained()->cascadeOnDelete(); // deleting a court clears its line
            // Exactly one of user_id / group_id is set (enforced by a CHECK below).
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('group_id')->nullable()->constrained()->cascadeOnDelete();

            $table->unsignedTinyInteger('players_count'); // 1 (solo) or 2/4 (group)
            $table->unsignedInteger('position')->nullable(); // NULL once the unit is off the line
            $table->enum('status', ['waiting', 'on_court', 'completed', 'skipped'])->default('waiting');

            $table->timestamp('joined_at')->useCurrent();
            $table->timestamp('called_at')->nullable();   // promoted to court
            $table->timestamp('resolved_at')->nullable(); // completed or skipped

            // One court has at most one unit per queue position (NULLs are allowed).
            $table->unique(['court_id', 'position']);
            $table->index(['court_id', 'status']); // per-court line + board filters
            $table->index('status');
            $table->index('user_id');
            $table->index('group_id');
        });

        $driver = DB::connection()->getDriverName();

        // A queue slot belongs to exactly one party: a solo user or a group.
        DB::statement(
            $driver === 'pgsql'
                ? 'ALTER TABLE queue_entries ADD CONSTRAINT queue_entries_exactly_one_party_check
                   CHECK ((user_id IS NOT NULL)::int + (group_id IS NOT NULL)::int = 1)'
                : 'ALTER TABLE queue_entries ADD CONSTRAINT queue_entries_exactly_one_party_check
                   CHECK ((user_id IS NOT NULL) + (group_id IS NOT NULL) = 1)' // MySQL/MariaDB coerce booleans to 1/0
        );

        // A unit is 1 solo player, or a 2/4 player group.
        DB::statement(
            'ALTER TABLE queue_entries ADD CONSTRAINT queue_entries_players_count_check
             CHECK (players_count BETWEEN 1 AND 4)'
        );

        // position is tracked only while the unit is still waiting.
        DB::statement(
            'ALTER TABLE queue_entries ADD CONSTRAINT queue_entries_position_status_check
             CHECK ((position IS NULL) = (status <> \'waiting\'))'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('queue_entries'); // constraints are dropped with the table
    }
};
