<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A "party" of 2 or 4 players who join a queue as one fixed unit.
     * Membership lives in the group_user pivot table; group size (2 or 4)
     * is validated in the application layer since cross-table CHECK
     * constraints are not supported by MySQL/PostgreSQL.
     */
    public function up(): void
    {
        Schema::create('groups', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable(); // e.g. "The Pickle Crew"
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('created_by_user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('groups');
    }
};
