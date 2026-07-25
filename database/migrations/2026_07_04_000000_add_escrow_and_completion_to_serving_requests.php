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
            DB::statement("ALTER TABLE serving_requests MODIFY COLUMN status ENUM('pending', 'accepted', 'rejected', 'completion_requested', 'completed', 'canceled') DEFAULT 'pending'");

            Schema::table('serving_requests', function ($table) {
                $table->decimal('held_amount', 10, 2)->nullable()->after('message');
                $table->timestamp('held_at')->nullable()->after('held_amount');
                $table->integer('automatically_cancel_after')->default(14)->after('held_at');
                $table->timestamp('accepted_at')->nullable()->after('automatically_cancel_after');
                $table->timestamp('completion_requested_at')->nullable()->after('accepted_at');
                $table->timestamp('completed_at')->nullable()->after('completion_requested_at');
                $table->timestamp('canceled_at')->nullable()->after('completed_at');
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
        } else {
            Schema::table('serving_requests', function ($table) {
                $table->decimal('held_amount', 10, 2)->nullable()->after('message');
                $table->timestamp('held_at')->nullable()->after('held_amount');
                $table->integer('automatically_cancel_after')->default(14)->after('held_at');
                $table->timestamp('accepted_at')->nullable()->after('automatically_cancel_after');
                $table->timestamp('completion_requested_at')->nullable()->after('accepted_at');
                $table->timestamp('completed_at')->nullable()->after('completion_requested_at');
                $table->timestamp('canceled_at')->nullable()->after('completed_at');
            });
        }
    }

    public function down(): void
    {
        $driver = DB::getDriverName();

        if ($driver !== 'sqlite') {
            Schema::table('serving_requests', function ($table) {
                $table->dropColumn(['held_amount', 'held_at', 'automatically_cancel_after', 'accepted_at', 'completion_requested_at', 'completed_at', 'canceled_at']);
            });
        }

        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE serving_requests MODIFY COLUMN status ENUM('pending', 'accepted', 'rejected', 'completion_requested', 'completed') DEFAULT 'pending'");
        }

        if ($driver === 'sqlite') {
            Schema::drop('serving_requests');

            Schema::create('serving_requests', function ($table) {
                $table->id();
                $table->foreignId('serving_id')->constrained('servings')->restrictOnDelete();
                $table->foreignId('requester_id')->constrained('users')->restrictOnDelete();
                $table->text('message')->nullable();
                $table->string('status', 50)->default('pending');
                $table->timestamps();

                $table->index(['serving_id', 'status'], 'serving_request_status_index');
                $table->index('requester_id', 'serving_request_requester_index');
            });
        }
    }
};
