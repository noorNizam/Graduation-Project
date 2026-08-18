<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // عدد الخدمات المطلوبة (عمر المستخدم)
            $table->integer('services_requested_count')->default(0)->after('is_active');

            // العدادات الأسبوعية والشهرية
            $table->integer('weekly_service_count')->default(0)->after('services_requested_count');
            $table->timestamp('weekly_reset_at')->nullable()->after('weekly_service_count');
            $table->integer('monthly_service_count')->default(0)->after('weekly_reset_at');
            $table->timestamp('monthly_reset_at')->nullable()->after('monthly_service_count');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'services_requested_count',
                'weekly_service_count',
                'weekly_reset_at',
                'monthly_service_count',
                'monthly_reset_at',
            ]);
        });
    }
};
