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
        $path = config('database.connections.backup-control.database');
        if ($path !== ':memory:' && ! is_file($path)) {
            if (! is_dir(dirname($path))) {
                mkdir(dirname($path), 0700, true);
            }
            touch($path);
            chmod($path, 0600);
        }

        if (! Schema::connection('backup-control')->hasTable('jobs')) {
            Schema::connection('backup-control')->create('jobs', function (Blueprint $table): void {
                $table->id();
                $table->string('queue')->index();
                $table->longText('payload');
                $table->unsignedTinyInteger('attempts');
                $table->unsignedInteger('reserved_at')->nullable();
                $table->unsignedInteger('available_at');
                $table->unsignedInteger('created_at');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('backup-control')->dropIfExists('jobs');
    }
};
