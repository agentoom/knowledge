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
        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('knowledge_source_id')->constrained('knowledge_sources')->cascadeOnDelete();
            $table->string('path');
            $table->string('filename');
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size_bytes')->default(0);
            $table->string('content_hash')->nullable();
            $table->string('status')->default('discovered');
            $table->json('metadata')->nullable();
            $table->timestamp('parsed_at')->nullable();
            $table->timestamp('chunked_at')->nullable();
            $table->timestamp('indexed_at')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index(['knowledge_source_id', 'status']);
            $table->index(['content_hash']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('documents');
    }
};
