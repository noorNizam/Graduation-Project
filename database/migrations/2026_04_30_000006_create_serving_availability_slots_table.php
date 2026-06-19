<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('serving_availability_slots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('serving_id')->constrained('servings')->onDelete('cascade');
            $table->unsignedTinyInteger('day_of_week')->nullable(); // 0 = Sunday .. 6 = Saturday
            $table->date('date')->nullable(); // specific calendar date
            $table->time('start_time');
            $table->time('end_time');
            $table->string('notes')->nullable();
            $table->timestamps();

            $table->index(['serving_id', 'day_of_week', 'start_time'], 'serving_day_time_index');
            $table->index(['serving_id', 'date', 'start_time'], 'serving_date_time_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('serving_availability_slots');
    }
};
