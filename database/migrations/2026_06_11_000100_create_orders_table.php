<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('channel', 30);
            $table->string('channel_order_no', 64);
            $table->unique(['channel', 'channel_order_no'], 'orders_channel_order_unique');
            $table->string('customer_name', 50);
            $table->string('customer_phone', 30);
            $table->string('shipping_postcode', 10)->nullable();
            $table->string('shipping_address1');
            $table->string('shipping_address2')->nullable();
            $table->json('items');
            $table->json('payment')->nullable();
            $table->string('pccc', 20)->nullable();
            $table->string('status', 30)->default('received')->index();
            $table->json('raw');
            $table->timestamp('ordered_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
