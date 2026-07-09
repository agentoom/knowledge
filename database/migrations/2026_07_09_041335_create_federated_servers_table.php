<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('federated_servers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('endpoint_url');
            $table->text('auth_token')->nullable()->comment('Encrypted API key for the remote server');
            $table->boolean('is_active')->default(true);
            $table->integer('priority')->default(0);
            $table->json('remote_capabilities')->nullable()->comment('Synced capabilities from remote metadata registry');
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('federated_servers');
    }
};
