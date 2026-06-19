<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('complaints', function (Blueprint $table) {
            // إذا بدك تحدي رفع ملف واحد
            $table->string('attachment_path')->nullable()->after('description');
            $table->string('attachment_name')->nullable()->after('attachment_path');
            
            // أو إذا بدك أكتر من ملف (حد 3)
            // $table->string('attachment1_path')->nullable();
            // $table->string('attachment1_name')->nullable();
            // $table->string('attachment2_path')->nullable();
            // $table->string('attachment2_name')->nullable();
            // $table->string('attachment3_path')->nullable();
            // $table->string('attachment3_name')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('complaints', function (Blueprint $table) {
            $table->dropColumn(['attachment_path', 'attachment_name']);
        });
    }
};