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
        Schema::create('barcode_print_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('batch_id')
                ->constrained()
                ->cascadeOnDelete();
            $table->foreignId('printed_by')
                ->nullable()
                ->references('id')->on('users')
                ->nullOnDelete();
            $table->integer('copies')->default(1);
            $table->string('printer_name')->nullable();
            $table->string('reason')->nullable();
            $table->timestamp('printed_at');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('barcode_print_logs');
    }
};
