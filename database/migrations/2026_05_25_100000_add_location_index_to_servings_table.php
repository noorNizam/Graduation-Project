<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('servings', function (Blueprint $table) {
            $table->index(['location_lat', 'location_lng'], 'idx_servings_location');
        });
    }

    public function down(): void
    {
        Schema::table('servings', function (Blueprint $table) {
            $table->dropIndex('idx_servings_location');
        });
    }
};
