<?php

namespace App\Console\Commands;

use App\Services\DatabaseBackupService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('database-backups:restore {backup : Backup UUID} {--force : Confirm database replacement after pausing all external writers}')]
#[Description('Recover the application database from a validated backup without API authentication')]
class RestoreDatabaseBackup extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(DatabaseBackupService $backups): int
    {
        if (! $this->option('force')) {
            $this->error('Pause background workers and external writers, then use --force to confirm replacement.');

            return self::FAILURE;
        }

        $backups->recover($this->argument('backup'));
        $this->info('Database restored. All users must log in again.');

        return self::SUCCESS;
    }
}
