<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Inventory\StocklevelService;
use Illuminate\Http\Request;

class StockLevelController extends Controller
{
    public function index(Request $request, StockLevelService $service)
    {
        return $service->getAll(
            $request->query('warehouse_id')
        );
    }
}
