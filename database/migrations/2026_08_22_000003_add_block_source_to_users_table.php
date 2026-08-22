<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Records WHY the account is deactivated: 'admin' (manual block),
            // 'suspension' or 'ban' (penalty system). The suspension-expiry
            // scheduler may only lift blocks whose source it owns.
            $table->string('block_source')->nullable()->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('block_source');
        });
    }
};
