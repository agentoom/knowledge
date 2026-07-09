<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('providers', function (Blueprint $table) {
            $table->dropForeign(['knowledge_source_id']);
            $table->foreign('knowledge_source_id')
                ->references('id')
                ->on('knowledge_sources')
                ->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('providers', function (Blueprint $table) {
            $table->dropForeign(['knowledge_source_id']);
            $table->foreign('knowledge_source_id')
                ->references('id')
                ->on('knowledge_sources')
                ->nullOnDelete();
        });
    }
};
