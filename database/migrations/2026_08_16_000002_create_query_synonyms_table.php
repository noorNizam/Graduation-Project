<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('query_synonyms', function (Blueprint $table) {
            $table->id();
            $table->string('word', 255)->unique();
            $table->string('language', 2);
            $table->json('synonyms');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('query_synonyms');
    }
};
