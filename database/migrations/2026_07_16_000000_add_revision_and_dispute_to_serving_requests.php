<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE serving_requests MODIFY COLUMN status ENUM('pending', 'accepted', 'rejected', 'completion_requested', 'completed', 'canceled', 'disputed') DEFAULT 'pending'");

            Schema::table('serving_requests', function ($table) {
                $table->timestamp('disputed_at')->nullable()->after('canceled_at');
                $table->integer('revision_count')->default(0)->after('disputed_at');
            });

            Schema::table('complaints', function ($table) {
                $table->foreignId('serving_request_id')->nullable()->constrained('serving_requests')->nullOnDelete()->after('serving_id');
            });
        } elseif ($driver === 'sqlite') {
            Schema::drop('serving_requests');

            Schema::create('serving_requests', function ($table) {
                $table->id();
                $table->foreignId('serving_id')->constrained('servings')->restrictOnDelete();
                $table->foreignId('requester_id')->constrained('users')->restrictOnDelete();
                $table->text('message')->nullable();
                $table->decimal('held_amount', 10, 2)->nullable();
                $table->timestamp('held_at')->nullable();
                $table->integer('automatically_cancel_after')->default(14);
                $table->timestamp('accepted_at')->nullable();
                $table->timestamp('completion_requested_at')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->timestamp('canceled_at')->nullable();
                $table->timestamp('disputed_at')->nullable();
                $table->integer('revision_count')->default(0);
                $table->string('status', 50)->default('pending');
                $table->timestamps();

                $table->index(['serving_id', 'status'], 'serving_request_status_index');
                $table->index('requester_id', 'serving_request_requester_index');
            });

            Schema::table('complaints', function ($table) {
                $table->foreignId('serving_request_id')->nullable()->constrained('serving_requests')->nullOnDelete()->after('serving_id');
            });
        } else {
            Schema::table('serving_requests', function ($table) {
                $table->timestamp('disputed_at')->nullable()->after('canceled_at');
                $table->integer('revision_count')->default(0)->after('disputed_at');
            });

            Schema::table('complaints', function ($table) {
                $table->foreignId('serving_request_id')->nullable()->constrained('serving_requests')->nullOnDelete()->after('serving_id');
            });
        }
    }

    public function down(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE serving_requests MODIFY COLUMN status ENUM('pending', 'accepted', 'rejected', 'completion_requested', 'completed', 'canceled') DEFAULT 'pending'");

            Schema::table('serving_requests', function ($table) {
                $table->dropColumn(['disputed_at', 'revision_count']);
            });

            Schema::table('complaints', function ($table) {
                $table->dropForeign(['serving_request_id']);
                $table->dropColumn('serving_request_id');
            });
        } elseif ($driver === 'sqlite') {
            Schema::drop('serving_requests');

            Schema::create('serving_requests', function ($table) {
                $table->id();
                $table->foreignId('serving_id')->constrained('servings')->restrictOnDelete();
                $table->foreignId('requester_id')->constrained('users')->restrictOnDelete();
                $table->text('message')->nullable();
                $table->decimal('held_amount', 10, 2)->nullable();
                $table->timestamp('held_at')->nullable();
                $table->integer('automatically_cancel_after')->default(14);
                $table->timestamp('accepted_at')->nullable();
                $table->timestamp('completion_requested_at')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->timestamp('canceled_at')->nullable();
                $table->string('status', 50)->default('pending');
                $table->timestamps();

                $table->index(['serving_id', 'status'], 'serving_request_status_index');
                $table->index('requester_id', 'serving_request_requester_index');
            });

            Schema::table('complaints', function ($table) {
                $table->dropForeign(['serving_request_id']);
                $table->dropColumn('serving_request_id');
            });
        } else {
            Schema::table('serving_requests', function ($table) {
                $table->dropColumn(['disputed_at', 'revision_count']);
            });

            Schema::table('complaints', function ($table) {
                $table->dropForeign(['serving_request_id']);
                $table->dropColumn('serving_request_id');
            });
        }
    }
};
