<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Remove duplicate documents (keep the latest one per path)
        // so the unique index can be created cleanly.
        DB::statement(<<<'SQL'
            DELETE FROM documents
            WHERE id IN (
                SELECT id FROM (
                    SELECT id,
                           ROW_NUMBER() OVER (PARTITION BY path ORDER BY created_at DESC) AS rn
                    FROM documents
                ) AS ranked
                WHERE rn > 1
            )
        SQL);

        Schema::table('documents', function (Blueprint $table) {
            $table->unique('path');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->dropUnique(['path']);
        });
    }
};
