<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('top_performers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('serving_type_id')->constrained('serving_types')->cascadeOnDelete();
            $table->unsignedTinyInteger('rank');
            $table->dateTime('date');
            $table->timestamps();

            $table->unique(['serving_type_id', 'date', 'rank']);
            $table->unique(['serving_type_id', 'date', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('top_performers');
    }
};
