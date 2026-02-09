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
        Schema::create('stock_snapshots', function (Blueprint $table) {
            $table->id();

            $table->foreignId('warehouse_id')
                  ->constrained()
                  ->cascadeOnDelete();

            $table->foreignId('batch_id')
                  ->constrained()
                  ->cascadeOnDelete();

            $table->decimal('quantity', 15, 3);

            $table->date('snapshot_date');

            $table->timestamps();

            $table->unique([
                'warehouse_id',
                'batch_id',
                'snapshot_date'
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_snapshots');
    }
};
