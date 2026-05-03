<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('comment_reactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('comment_id')->constrained('comments')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->enum('type', ['like', 'dislike']);
            $table->timestamps();

            $table->unique(['comment_id', 'user_id'], 'comment_reaction_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('comment_reactions');
    }
};
