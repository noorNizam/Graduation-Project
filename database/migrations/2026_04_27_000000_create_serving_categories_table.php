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
        Schema::create('serving_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->foreignId('parent_id')->nullable()->index();
            $table->timestamps();
        });

        // add self-referencing foreign key after table creation (safer across DB engines)
        Schema::table('serving_categories', function (Blueprint $table) {
            $table->foreign('parent_id')
                ->references('id')
                ->on('serving_categories')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('serving_categories');
    }
};
