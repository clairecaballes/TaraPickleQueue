<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Extends the framework-provided users table
     * (0001_01_01_000000_create_users_table.php) with pickleball profile fields.
     *
     * An incremental migration is used so it composes cleanly with Laravel's
     * default auth scaffolding. If you prefer a single users migration, merge
     * these columns into the default one and drop this file instead.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Self-reported skill rating: 1.0 (novice) to 5.0 (expert).
            $table->decimal('skill_rating', 2, 1)->nullable()->after('email');
            $table->string('phone', 32)->nullable()->after('skill_rating');
            $table->string('avatar', 2048)->nullable()->after('phone');

            $table->index('skill_rating'); // frequent filter/sort for fair matching
            $table->index('phone');
        });

        // Enforce the 1.0 - 5.0 rating scale at the database level (NULL passes).
        DB::statement(
            'ALTER TABLE users ADD CONSTRAINT users_skill_rating_range_check
             CHECK (skill_rating >= 1.0 AND skill_rating <= 5.0)'
        );
    }

    public function down(): void
    {
        // MySQL drops CHECK constraints with DROP CHECK, PostgreSQL with DROP CONSTRAINT.
        DB::statement(
            DB::connection()->getDriverName() === 'pgsql'
                ? 'ALTER TABLE users DROP CONSTRAINT users_skill_rating_range_check'
                : 'ALTER TABLE users DROP CHECK users_skill_rating_range_check'
        );

        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['skill_rating']);
            $table->dropIndex(['phone']);
            $table->dropColumn(['skill_rating', 'phone', 'avatar']);
        });
    }
};
