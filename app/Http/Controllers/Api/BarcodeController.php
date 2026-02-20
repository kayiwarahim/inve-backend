<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Barcode;
use Illuminate\Http\Request;

class BarcodeController extends Controller
{
    public function index()
    {
        return Barcode::with('batch')->get();
    }

    public function store(Request $request)
    {
        // Validate: batch_id exists, code unique, type
        $barcode = Barcode::create([
            'batch_id' => $request->batch_id,
            'code' => $request->code ?? $this->generateCode(),
            'type' => $request->type,
            'generated_at' => now(),
        ]);
        return response()->json($barcode, 201);
    }

    public function show(Barcode $barcode)
    {
        return $barcode->load('batch');
    }

    public function update(Request $request, Barcode $barcode)
    {
        $barcode->update($request->only(['type']));
        return $barcode;
    }

    public function destroy(Barcode $barcode)
    {
        $barcode->delete();
        return response()->json(null, 204);
    }

    private function generateCode()
    {
        return 'BAR-' . strtoupper(uniqid());
    }
}