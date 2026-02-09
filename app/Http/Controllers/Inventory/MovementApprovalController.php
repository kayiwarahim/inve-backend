<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\MovementApproval;
use App\Services\Inventory\MovementApprovalService;
use Illuminate\Http\Request;

class MovementApprovalController extends Controller
{
    public function approve(MovementApproval $approval, MovementApprovalService $service)
    {
        return $service->approve($approval);
    }

    public function reject(Request $request, MovementApproval $approval, MovementApprovalService $service)
    {
        return $service->reject($approval, $request->input('remarks'));
    }
}
