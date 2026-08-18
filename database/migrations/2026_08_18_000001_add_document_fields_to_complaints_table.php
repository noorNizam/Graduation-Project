<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('complaints', function (Blueprint $table) {
            $table->string('documents_requested_from')->nullable()->after('attachment_name');
            $table->timestamp('documents_due_at')->nullable()->after('documents_requested_from');
            $table->boolean('complainant_documents_uploaded')->default(false)->after('documents_due_at');
            $table->boolean('accused_documents_uploaded')->default(false)->after('complainant_documents_uploaded');
        });

        Schema::table('complaints', function (Blueprint $table) {
            $table->enum('status', [
                'pending',
                'awaiting_documents',
                'under_review',
                'resolved',
                'rejected',
            ])->default('pending')->change();
        });
    }

    public function down(): void
    {
        Schema::table('complaints', function (Blueprint $table) {
            $table->enum('status', [
                'pending',
                'under_review',
                'resolved',
                'rejected',
            ])->default('pending')->change();
        });

        Schema::table('complaints', function (Blueprint $table) {
            $table->dropColumn([
                'documents_requested_from',
                'documents_due_at',
                'complainant_documents_uploaded',
                'accused_documents_uploaded',
            ]);
        });
    }
};
