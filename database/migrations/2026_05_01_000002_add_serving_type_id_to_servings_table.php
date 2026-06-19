<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('servings', function (Blueprint $table) {
            $table->foreignId('serving_type_id')->nullable()->constrained('serving_types')->onDelete('restrict')->after('unit_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('servings', function (Blueprint $table) {
            $table->dropForeign(['serving_type_id']);
            $table->dropColumn('serving_type_id');
        });
    }
};
