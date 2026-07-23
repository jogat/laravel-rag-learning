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
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained();
            $table->string('order_number');
            $table->foreignId('user_id')->nullable()->constrained();
            $table->string('status');
            $table->date('estimated_delivery_date');
            $table->json('items');
            $table->timestamps();

            // Los números de pedido son únicos POR negocio, no globalmente.
            $table->unique(['project_id', 'order_number']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
