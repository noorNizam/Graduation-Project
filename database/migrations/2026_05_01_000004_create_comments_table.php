<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('serving_id')->constrained('servings')->restrictOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('comments')->cascadeOnDelete();
            $table->tinyInteger('depth')->default(0);
            $table->text('content');
            $table->timestamps();

            $table->index(['serving_id', 'parent_id'], 'comment_serving_parent_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('comments');
    }
};
