<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Widen FIRST: legacy rows still hold 'rejected', and MySQL strict
        // mode refuses to write values the column does not accept yet.
        Schema::table('complaints', function (Blueprint $table) {
            $table->enum('status', [
                'pending',
                'awaiting_documents',
                'under_review',
                'resolved',
                'rejected',
            ])->default('pending')->change();
        });

        // 'rejected' left the vocabulary: every complaint must end in an
        // explicit verdict. Legacy procedural closures map to a dismissal
        // (unjustified = no penalty), stamped as system-converted. Row-by-row
        // so the SQL stays portable (no NOW()/CONCAT on SQLite).
        $convertedAt = now();

        DB::table('complaints')
            ->where('status', 'rejected')
            ->orderBy('id')
            ->chunkById(500, function ($rows) use ($convertedAt) {
                foreach ($rows as $row) {
                    DB::table('complaints')
                        ->where('id', $row->id)
                        ->update([
                            'status' => 'resolved',
                            'outcome' => 'unjustified',
                            'resolved_at' => $convertedAt,
                            'admin_note' => trim((($row->admin_note ?? '') !== '' ? $row->admin_note.' ' : '').'[converted from rejected]'),
                        ]);
                }
            });

        // Narrow to the final four-state vocabulary.
        Schema::table('complaints', function (Blueprint $table) {
            $table->enum('status', [
                'pending',
                'awaiting_documents',
                'under_review',
                'resolved',
            ])->default('pending')->change();
        });
    }

    public function down(): void
    {
        // Reintroduce the legacy states so pre-migration code can boot.
        // Converted rows stay resolved — the original status cannot be
        // distinguished from genuine resolutions.
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
};
