<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('serving_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('serving_id')->constrained('servings')->restrictOnDelete();
            $table->foreignId('requester_id')->constrained('users')->restrictOnDelete();
            $table->text('message')->nullable();
            $table->enum('status', ['pending', 'accepted', 'rejected'])->default('pending');
            $table->timestamps();

            $table->index(['serving_id', 'status'], 'serving_request_status_index');
            $table->index('requester_id', 'serving_request_requester_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('serving_requests');
    }
};
