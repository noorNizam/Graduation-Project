<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scheduler_logs', function (Blueprint $table) {
            $table->id();
            $table->string('job_name');
            $table->dateTime('started_at');
            $table->dateTime('finished_at');
            $table->json('errors')->nullable();
            $table->timestamps();

            $table->index('job_name');
            $table->index('started_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scheduler_logs');
    }
};
