<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Adds the intermediate "called" status so players have a 2-minute window to
     * respond to a court call before being auto-skipped.
     *
     * Lifecycle: waiting -> called -> on_court -> completed | skipped
     *
     * PostgreSQL stores the status as varchar + CHECK constraint (from Laravel's
     * enum compilation), so the check must be recreated. MySQL and SQLite are
     * handled by the native schema builder.
     */
    public function up(): void
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE queue_entries DROP CONSTRAINT queue_entries_status_check');
            DB::statement('ALTER TABLE queue_entries ALTER COLUMN status TYPE varchar(255)');
            DB::statement("ALTER TABLE queue_entries ALTER COLUMN status SET DEFAULT 'waiting'");
            DB::statement(
                "ALTER TABLE queue_entries ADD CONSTRAINT queue_entries_status_check
                 CHECK (status IN ('waiting','called','on_court','completed','skipped'))"
            );

            return;
        }

        Schema::table('queue_entries', function (Blueprint $table) {
            $table->enum('status', ['waiting', 'called', 'on_court', 'completed', 'skipped'])
                ->default('waiting')
                ->change();
        });
    }

    public function down(): void
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE queue_entries DROP CONSTRAINT queue_entries_status_check');
            DB::statement('ALTER TABLE queue_entries ALTER COLUMN status TYPE varchar(255)');
            DB::statement("ALTER TABLE queue_entries ALTER COLUMN status SET DEFAULT 'waiting'");
            DB::statement(
                "ALTER TABLE queue_entries ADD CONSTRAINT queue_entries_status_check
                 CHECK (status IN ('waiting','on_court','completed','skipped'))"
            );

            return;
        }

        Schema::table('queue_entries', function (Blueprint $table) {
            $table->enum('status', ['waiting', 'on_court', 'completed', 'skipped'])
                ->default('waiting')
                ->change();
        });
    }
};
