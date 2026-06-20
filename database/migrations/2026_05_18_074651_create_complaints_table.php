<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('complaints', function (Blueprint $table) {
            $table->id();

            $table->foreignId('serving_id')
                ->constrained()
                ->onDelete('cascade');

            $table->foreignId('complainant_id')
                ->constrained('users')
                ->onDelete('cascade');

            $table->foreignId('accused_user_id')
                ->constrained('users')
                ->onDelete('cascade');

            $table->string('reason');

            $table->text('description')->nullable();

            $table->enum('status', [
                'pending',
                'under_review',
                'resolved',
                'rejected',
            ])->default('pending');

            $table->text('admin_note')->nullable();

            $table->string('attachment_path')->nullable();
            $table->string('attachment_name')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('complaints');
    }
};
