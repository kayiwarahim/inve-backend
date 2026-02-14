<?php

namespace App\Services;

use App\Models\MovementApproval;
use Carbon\Carbon;

class MovementApprovalService
{
    public function approve(MovementApproval $approval, ?string $remarks = null): MovementApproval
    {
        $approval->update([
            'status'      => 'APPROVED',
            'approved_by'=> auth()->id(),
            'approved_at'=> Carbon::now(),
            'remarks'    => $remarks
        ]);

        return $approval;
    }

    public function reject(MovementApproval $approval, ?string $remarks = null): MovementApproval
    {
        $approval->update([
            'status'      => 'REJECTED',
            'approved_by'=> auth()->id(),
            'approved_at'=> Carbon::now(),
            'remarks'    => $remarks
        ]);

        return $approval;
    }
}
