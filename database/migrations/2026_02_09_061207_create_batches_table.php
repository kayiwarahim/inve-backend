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
        Schema::create('batches', function (Blueprint $table) {
            $table->id();

            $table->foreignId('product_id')
                  ->constrained()
                  ->cascadeOnDelete();

            $table->string('barcode')->unique(); // printed on label
            $table->string('batch_code')->nullable();

            $table->date('manufactured_at')->nullable();
            $table->date('expires_at')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('batches');
    }
};
