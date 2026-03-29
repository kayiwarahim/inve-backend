<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('source_lots', function (Blueprint $table) {
            $table->decimal('gross_car_weight_kg', 12, 3)->nullable()->after('source_lot_code');
            $table->unsignedInteger('chicken_tray_count')->nullable()->after('gross_car_weight_kg');
            $table->decimal('tray_unit_weight_kg', 12, 3)->nullable()->after('chicken_tray_count');
        });
    }

    public function down(): void
    {
        Schema::table('source_lots', function (Blueprint $table) {
            $table->dropColumn([
                'gross_car_weight_kg',
                'chicken_tray_count',
                'tray_unit_weight_kg',
            ]);
        });
    }
};
