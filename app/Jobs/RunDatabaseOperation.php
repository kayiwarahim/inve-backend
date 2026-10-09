<?php

namespace App\Jobs;

use App\Services\DatabaseBackupService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class RunDatabaseOperation implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 3600;

    public bool $failOnTimeout = true;

    public function __construct(public string $operationId) {}

    public function handle(DatabaseBackupService $backups): void
    {
        $backups->run($this->operationId);
    }

    public function failed(?Throwable $exception): void
    {
        app(DatabaseBackupService::class)->fail($this->operationId);
    }
}
