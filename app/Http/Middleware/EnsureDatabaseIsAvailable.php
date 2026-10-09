<?php

namespace App\Http\Middleware;

use App\Services\DatabaseBackupService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureDatabaseIsAvailable
{
    public function __construct(private DatabaseBackupService $backups) {}

    public function handle(Request $request, Closure $next): Response
    {
        $backups = $this->backups;
        if (is_file($backups->path('restoring'))) {
            return response()->json(['message' => 'Database restoration is in progress or requires recovery.'], 503, ['Retry-After' => '30']);
        }

        $lock = fopen($backups->path('traffic.lock'), 'c+');
        abort_if($lock === false, 503, 'Database availability could not be checked.');
        if (! flock($lock, LOCK_SH | LOCK_NB)) {
            fclose($lock);

            return response()->json(['message' => 'Database restoration is in progress.'], 503, ['Retry-After' => '30']);
        }

        try {
            return $next($request);
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }
}
