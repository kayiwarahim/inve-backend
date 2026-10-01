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
        Schema::table('users', function (Blueprint $table) {
            $table->text('mfa_secret')->nullable();
            $table->timestamp('mfa_confirmed_at')->nullable();
            $table->text('mfa_pending_secret')->nullable();
            $table->timestamp('mfa_pending_expires_at')->nullable();
            $table->text('mfa_recovery_codes')->nullable();
            $table->unsignedBigInteger('mfa_last_used_step')->nullable();
            $table->string('mfa_challenge_hash', 64)->nullable()->unique();
            $table->timestamp('mfa_challenge_expires_at')->nullable();
            $table->unsignedTinyInteger('mfa_challenge_attempts')->default(0);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['mfa_challenge_hash']);
            $table->dropColumn([
                'mfa_secret', 'mfa_confirmed_at', 'mfa_pending_secret',
                'mfa_pending_expires_at', 'mfa_recovery_codes', 'mfa_last_used_step',
                'mfa_challenge_hash', 'mfa_challenge_expires_at', 'mfa_challenge_attempts',
            ]);
        });
    }
};
