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
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('batch_id')
                  ->constrained()
                  ->cascadeOnDelete();
            $table->foreignId('from_warehouse_id')
                  ->nullable()
                  ->references('id')->on('warehouses')
                  ->nullOnDelete();
            $table->foreignId('to_warehouse_id')
                  ->nullable()
                  ->references('id')->on('warehouses')
                  ->nullOnDelete();
            $table->enum('movement_type', [
                'IN',
                'OUT',
                'TRANSFER',
                'ADJUSTMENT'
            ]);

            $table->decimal('quantity', 15, 3);

            $table->foreignId('performed_by')
                  ->references('id')->on('users')
                  ->nullOnDelete();

            $table->string('reference_no')->nullable();
            $table->text('remarks')->nullable();

            $table->timestamp('performed_at');

            $table->timestamps();

            $table->index(['batch_id', 'movement_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
    }
};
