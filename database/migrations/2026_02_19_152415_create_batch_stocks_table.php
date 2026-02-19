<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('batch_stocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('batch_id')->constrained()->onDelete('cascade');
            $table->foreignId('warehouse_id')->constrained()->onDelete('cascade');
            $table->integer('quantity')->default(0);
            $table->timestamps();

            // Unique constraint to prevent duplicate entries for same batch-warehouse
            $table->unique(['batch_id', 'warehouse_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('batch_stocks');
    }
};