<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasTable('audit_logs')) {
            return;
        }

        DB::table('audit_logs')
            ->orderBy('id')
            ->chunkById(500, function ($auditLogs): void {
                $rows = [];

                foreach ($auditLogs as $auditLog) {
                    $rows[] = [
                        'log_name' => 'audit',
                        'description' => $auditLog->action,
                        'subject_type' => null,
                        'subject_id' => null,
                        'event' => 'request',
                        'causer_type' => $auditLog->user_id ? 'App\\Models\\User' : null,
                        'causer_id' => $auditLog->user_id,
                        'attribute_changes' => null,
                        'properties' => json_encode([
                            'route_action' => $auditLog->auditable_type,
                            'auditable_id' => $auditLog->auditable_id,
                            'request_payload' => json_decode($auditLog->old_values ?? 'null', true),
                            'response' => json_decode($auditLog->new_values ?? 'null', true),
                            'ip_address' => $auditLog->ip_address,
                            'user_agent' => $auditLog->user_agent,
                        ]),
                        'created_at' => $auditLog->performed_at ?? $auditLog->created_at,
                        'updated_at' => $auditLog->updated_at ?? $auditLog->performed_at ?? $auditLog->created_at,
                    ];
                }

                DB::table('activity_log')->insert($rows);
            });

        Schema::dropIfExists('audit_logs');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action');
            $table->string('auditable_type');
            $table->unsignedBigInteger('auditable_id');
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->ipAddress('ip_address')->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamp('performed_at');
            $table->timestamps();
            $table->index(['auditable_type', 'auditable_id']);
        });

        if (! Schema::hasTable('activity_log')) {
            return;
        }

        DB::table('activity_log')
            ->where('log_name', 'audit')
            ->orderBy('id')
            ->chunkById(500, function ($activities): void {
                $rows = [];

                foreach ($activities as $activity) {
                    $properties = json_decode($activity->properties ?? '[]', true) ?: [];

                    $rows[] = [
                        'user_id' => $activity->causer_type === 'App\\Models\\User' ? $activity->causer_id : null,
                        'action' => $activity->description,
                        'auditable_type' => $properties['route_action'] ?? $activity->subject_type ?? 'unknown',
                        'auditable_id' => $properties['auditable_id'] ?? $activity->subject_id ?? 0,
                        'old_values' => json_encode($properties['request_payload'] ?? null),
                        'new_values' => json_encode($properties['response'] ?? null),
                        'ip_address' => $properties['ip_address'] ?? null,
                        'user_agent' => $properties['user_agent'] ?? null,
                        'performed_at' => $activity->created_at,
                        'created_at' => $activity->created_at,
                        'updated_at' => $activity->updated_at,
                    ];
                }

                DB::table('audit_logs')->insert($rows);
            });
    }
};
