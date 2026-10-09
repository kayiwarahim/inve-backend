<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\RestoreDatabaseBackupRequest;
use App\Http\Requests\Api\UploadDatabaseBackupRequest;
use App\Services\DatabaseBackupService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class DatabaseBackupController extends Controller
{
    public function __construct(private DatabaseBackupService $backups) {}

    public function index(): JsonResponse
    {
        return response()->json(['data' => $this->backups->backups()]);
    }

    public function store(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->backups->start('backup', $request->user()->id)], 202);
    }

    public function upload(UploadDatabaseBackupRequest $request): JsonResponse
    {
        return response()->json(['data' => $this->backups->upload($request->file('backup'), $request->user()->id)], 201);
    }

    public function download(string $backup): BinaryFileResponse
    {
        return response()->download($this->backups->archivePath($backup), 'database-backup-'.$backup.'.zip', [
            'Cache-Control' => 'no-store, private',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function restore(RestoreDatabaseBackupRequest $request, string $backup): JsonResponse
    {
        return response()->json(['data' => $this->backups->start('restore', $request->user()->id, $backup)], 202);
    }

    public function operation(string $operation): JsonResponse
    {
        return response()->json(['data' => $this->backups->operation($operation)], headers: ['Cache-Control' => 'no-store']);
    }
}
