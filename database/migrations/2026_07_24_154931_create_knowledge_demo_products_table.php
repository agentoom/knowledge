<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('knowledge_demo_products', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('category');
            $table->decimal('price', 10, 2);
            $table->text('description')->nullable();
            $table->string('sku')->unique();
            $table->integer('stock')->default(0);
            $table->timestamps();
        });

        DB::table('knowledge_demo_products')->insert([
            ['name' => 'Wireless Keyboard', 'category' => 'Electronics', 'price' => 79.99, 'description' => 'Ergonomic wireless keyboard with backlit keys.', 'sku' => 'KB-001', 'stock' => 150, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'USB-C Hub', 'category' => 'Accessories', 'price' => 49.99, 'description' => '7-in-1 USB-C hub with HDMI, SD card, and 3 USB ports.', 'sku' => 'HUB-002', 'stock' => 300, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Monitor Stand', 'category' => 'Office', 'price' => 129.99, 'description' => 'Adjustable aluminum monitor stand with cable management.', 'sku' => 'MS-003', 'stock' => 75, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Noise Cancelling Headphones', 'category' => 'Audio', 'price' => 199.99, 'description' => 'Over-ear ANC headphones with 30-hour battery life.', 'sku' => 'HP-004', 'stock' => 200, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Mechanical Keyboard', 'category' => 'Electronics', 'price' => 149.99, 'description' => 'RGB mechanical keyboard with Cherry MX switches.', 'sku' => 'KB-005', 'stock' => 100, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Webcam 4K', 'category' => 'Electronics', 'price' => 89.99, 'description' => '4K webcam with auto-focus and built-in microphone.', 'sku' => 'WC-006', 'stock' => 250, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Laptop Stand', 'category' => 'Office', 'price' => 39.99, 'description' => 'Portable foldable laptop stand with anti-slip pads.', 'sku' => 'LS-007', 'stock' => 500, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'External SSD 1TB', 'category' => 'Storage', 'price' => 109.99, 'description' => 'Portable 1TB external SSD with USB-C connectivity.', 'sku' => 'SSD-008', 'stock' => 180, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('knowledge_demo_products');
    }
};
