<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lot_transformation_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lot_transformation_id')->constrained()->cascadeOnDelete();
            $table->enum('line_type', ['input', 'output', 'byproduct', 'waste']);
            $table->foreignId('batch_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('source_lot_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('weight_kg', 12, 3);
            $table->text('remarks')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lot_transformation_lines');
    }
};
