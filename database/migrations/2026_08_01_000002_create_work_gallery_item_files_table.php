<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('work_gallery_item_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('work_gallery_item_id')->constrained('work_gallery_items')->onDelete('cascade');
            $table->string('file_url', 2048);
            $table->string('file_type', 100)->nullable();
            $table->timestamps();

            $table->index('work_gallery_item_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('work_gallery_item_files');
    }
};
