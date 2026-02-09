<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Services\Inventory\StockLevelService;
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
