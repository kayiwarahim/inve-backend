<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('batch_stocks', function (Blueprint $table) {
            $table->decimal('quantity', 12, 3)->default(0)->change();
        });

        Schema::table('stock_transactions', function (Blueprint $table) {
            $table->decimal('quantity', 12, 3)->change();
        });
    }

    public function down(): void
    {
        Schema::table('batch_stocks', function (Blueprint $table) {
            $table->integer('quantity')->default(0)->change();
        });

        Schema::table('stock_transactions', function (Blueprint $table) {
            $table->integer('quantity')->change();
        });
    }
};
