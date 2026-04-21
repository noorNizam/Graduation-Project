<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('email_verification_attempts', function (Blueprint $table) {
            $table->id();
            $table->string('email')->index();
            $table->string('otp', 6);
            $table->enum('status', ['active', 'used'])->default('active');
            $table->timestamp('expires_at');
            $table->timestamps();
            
            $table->index(['email', 'status']);
            $table->index(['email', 'expires_at']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('email_verification_attempts');
    }
};