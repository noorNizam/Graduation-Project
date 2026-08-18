<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('identity_verifications', function (Blueprint $table) {
            $table->string('vendor_token')->nullable()->unique()->after('session_id');
            $table->string('verification_url')->nullable()->after('vendor_token');
            $table->index('session_id');
        });
    }

    public function down(): void
    {
        Schema::table('identity_verifications', function (Blueprint $table) {
            $table->dropUnique(['vendor_token']);
            $table->dropIndex(['session_id']);
            $table->dropColumn(['vendor_token', 'verification_url']);
        });
    }
};
