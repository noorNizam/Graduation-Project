<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_ratings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('serving_id')->constrained('servings')->cascadeOnDelete();
            $table->decimal('rating', 3, 1)->default(0);
            $table->timestamps();

            $table->unique(['user_id', 'serving_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_ratings');
    }
};
