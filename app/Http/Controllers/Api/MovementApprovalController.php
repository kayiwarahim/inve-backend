<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\MovementApprovalService;

class MovementApprovalController extends Controller
{
    public function approve($id, MovementApprovalService $service)
    {
        $service->approve($id);

        return response()->json([
            'message' => 'Stock movement approved and applied.'
        ]);
    }

    public function reject($id, MovementApprovalService $service)
    {
        $service->reject($id);

        return response()->json([
            'message' => 'Stock movement rejected.'
        ]);
    }
}
