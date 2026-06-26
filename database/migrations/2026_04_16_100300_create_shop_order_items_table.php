<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shop_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_order_id')->constrained('shop_orders')->onDelete('cascade');
            $table->foreignId('shop_product_id')->constrained('shop_products')->onDelete('restrict');
            $table->integer('quantity');
            $table->integer('unit_price'); // en centimes
            $table->integer('item_total'); // en centimes
            $table->timestamps();

            $table->index('shop_order_id');
            $table->index('shop_product_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shop_order_items');
    }
};
