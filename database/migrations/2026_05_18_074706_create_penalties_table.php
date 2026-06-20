<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('penalties', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->constrained()
                ->onDelete('cascade');

            $table->foreignId('complaint_id')
                ->nullable()
                ->constrained()
                ->onDelete('cascade');

            $table->enum('type', [
                'warning',
                'deduct_hours',
                'suspend',
                'ban',
            ]);

            $table->integer('hours_deducted')->nullable();

            $table->text('reason')->nullable();

            $table->boolean('is_active')->default(true);

            $table->timestamp('expires_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('penalties');
    }
};
