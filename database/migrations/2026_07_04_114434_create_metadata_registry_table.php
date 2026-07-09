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
        Schema::create('metadata_registry', function (Blueprint $table) {
            $table->id();
            $table->jsonb('payload');
            $table->integer('version')->default(1);
            $table->string('checksum')->nullable();
            $table->timestamp('built_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('metadata_registry');
    }
};
