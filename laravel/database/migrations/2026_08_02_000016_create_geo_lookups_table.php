<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Cache for IP -> location lookups (GeoIpService). Resolved values are kept
     * for a day so the visitor middleware never hammers the geolocation API.
     */
    public function up(): void
    {
        Schema::create('geo_lookups', function (Blueprint $table) {
            $table->id();
            $table->string('ip', 45)->unique();
            $table->string('country', 80)->nullable();
            $table->string('region', 120)->nullable();
            $table->string('city', 120)->nullable();
            $table->timestamp('resolved_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('geo_lookups');
    }
};
