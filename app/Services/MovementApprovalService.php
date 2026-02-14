<?php

namespace App\Services;

use App\Models\MovementApproval;
use App\Services\StockLevelService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

use Exception;

class MovementApprovalService
{
    public function approve(int $approvalId): void
    {
        DB::transaction(function () use ($approvalId) {

            $approval = MovementApproval::with('movement')
                ->lockForUpdate()
                ->findOrFail($approvalId);

            if ($approval->status !== 'PENDING') {
                throw new Exception('Approval already processed.');
            }

            $approval->update([
                'status'      => 'APPROVED',
                'approved_at' => now(),
                'approved_by' => Auth::id()
            ]);

            $approval->movement->update([
                'status' => 'approved'
            ]);

            app(StockLevelService::class)
                ->applyMovement($approval->movement);
        });
    }

    public function reject(int $approvalId, string $reason = null): void
    {
        $approval = MovementApproval::findOrFail($approvalId);

        if ($approval->status !== 'PENDING') {
            throw new Exception('Approval already processed.');
        }

        $approval->update([
            'status' => 'REJECTED',
            'remarks' => $reason,
            'rejected_at' => now(),
            'rejected_by' => Auth::id()
        ]);

        $approval->movement->update([
            'status' => 'rejected'
        ]);
    }
}
