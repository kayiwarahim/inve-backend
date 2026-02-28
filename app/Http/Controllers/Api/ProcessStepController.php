<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreProcessStepRequest;
use App\Http\Requests\Api\UpdateProcessStepRequest;
use App\Models\ProcessStep;
use Illuminate\Http\JsonResponse;

class ProcessStepController extends Controller
{
    public function index(): JsonResponse
    {
        $steps = ProcessStep::query()
            ->orderBy('sequence')
            ->orderBy('id')
            ->get();

        return response()->json($steps, 200);
    }

    public function store(StoreProcessStepRequest $request): JsonResponse
    {
        $step = ProcessStep::query()->create($request->validated());

        return response()->json([
            'message' => 'Process step created successfully',
            'data' => $step,
        ], 200);
    }

    public function show(ProcessStep $processStep): JsonResponse
    {
        return response()->json($processStep, 200);
    }

    public function update(UpdateProcessStepRequest $request, ProcessStep $processStep): JsonResponse
    {
        $processStep->update($request->validated());

        return response()->json([
            'message' => 'Process step updated successfully',
            'data' => $processStep->fresh(),
        ], 200);
    }

    public function destroy(ProcessStep $processStep): JsonResponse
    {
        $processStep->delete();

        return response()->json([
            'message' => 'Process step deleted successfully',
        ], 200);
    }
}
