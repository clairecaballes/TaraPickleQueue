<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('courts', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('location')->nullable();
            $table->enum('play_type', ['singles', 'doubles'])->default('doubles');
            $table->unsignedTinyInteger('max_players')->default(4); // 2 (singles) or 4 (doubles)
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('is_active');
            $table->index(['is_active', 'play_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('courts');
    }
};
