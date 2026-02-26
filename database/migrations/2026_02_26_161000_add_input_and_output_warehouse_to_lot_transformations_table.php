<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lot_transformations', function (Blueprint $table) {
            $table->foreignId('input_warehouse_id')
                ->nullable()
                ->after('warehouse_id')
                ->constrained('warehouses')
                ->nullOnDelete();
            $table->foreignId('output_warehouse_id')
                ->nullable()
                ->after('input_warehouse_id')
                ->constrained('warehouses')
                ->nullOnDelete();
        });

        DB::table('lot_transformations')->update([
            'input_warehouse_id' => DB::raw('warehouse_id'),
            'output_warehouse_id' => DB::raw('warehouse_id'),
        ]);
    }

    public function down(): void
    {
        Schema::table('lot_transformations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('output_warehouse_id');
            $table->dropConstrainedForeignId('input_warehouse_id');
        });
    }
};
