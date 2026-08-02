<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('group_user', function (Blueprint $table) {
            $table->foreignId('group_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            // A player can belong to a group only once.
            $table->primary(['group_id', 'user_id']);

            // Reverse lookup: all groups a user is part of.
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('group_user');
    }
};
