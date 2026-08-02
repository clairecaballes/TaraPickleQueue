<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Links an on-court queue entry to the team its players were assigned to.
     * This lets match completion resolve the winning/losing queue units for the
     * court rotation rule without re-deriving membership from team_user.
     */
    public function up(): void
    {
        Schema::table('queue_entries', function (Blueprint $table) {
            $table->foreignId('team_id')->nullable()->after('group_id')->constrained('teams')->nullOnDelete();
            $table->index('team_id');
        });
    }

    public function down(): void
    {
        Schema::table('queue_entries', function (Blueprint $table) {
            $table->dropIndex(['team_id']);
            $table->dropConstrainedForeignId('team_id');
        });
    }
};
