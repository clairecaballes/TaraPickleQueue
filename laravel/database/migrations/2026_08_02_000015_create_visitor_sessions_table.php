<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One row = one browser session (a visitor identified by the tp_visitor
     * cookie). Hits increment on every page load inside the active window, so
     * the admin dashboard can chart unique vs. returning visitors, average
     * session length, peak hours and geographic origin.
     */
    public function up(): void
    {
        Schema::create('visitor_sessions', function (Blueprint $table) {
            $table->id();
            $table->string('visitor_id')->index();          // tp_visitor cookie (uuid)
            $table->string('session_id')->unique();         // tp_session cookie (uuid)
            $table->string('ip', 45)->nullable();           // IPv4 / IPv6
            $table->string('user_agent')->nullable();
            $table->string('country', 80)->nullable();      // resolved via GeoIpService
            $table->string('region', 120)->nullable();
            $table->string('city', 120)->nullable();
            $table->string('path')->nullable();             // first page of the session
            $table->unsignedInteger('hits')->default(1);    // page loads in this session
            $table->timestamp('started_at');
            $table->timestamp('last_activity_at');
            $table->timestamp('ended_at')->nullable();      // set when the session goes stale
            $table->timestamps();

            $table->index('started_at');
            $table->index('country');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('visitor_sessions');
    }
};
