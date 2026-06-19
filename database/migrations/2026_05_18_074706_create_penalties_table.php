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
        Schema::create('penalties', function (Blueprint $table) {

            $table->id();
        
            $table->foreignId('complaint_id')
                ->constrained()
                ->onDelete('cascade');
        
            $table->foreignId('user_id');
        
            $table->enum('type', [
                'warning',
                'temporary_ban',
                'permanent_ban',
                'deduct_hours'
            ]);
        
            $table->text('reason');
        
            $table->integer('duration')->nullable();
        
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('penalties');
    }
};
