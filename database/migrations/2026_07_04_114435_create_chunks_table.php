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
        Schema::create('chunks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained('documents')->cascadeOnDelete();
            $table->integer('sequence');
            $table->text('content');
            $table->integer('token_count')->default(0);
            $table->string('embedding_hash')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('indexed_at')->nullable();
            $table->string('vector_store_id')->nullable();
            $table->timestamps();

            $table->index(['document_id', 'sequence']);
            $table->index(['indexed_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('chunks');
    }
};
