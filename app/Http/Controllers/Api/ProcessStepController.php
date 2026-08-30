<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\PaginatedIndexRequest;
use App\Http\Requests\Api\StoreProcessStepRequest;
use App\Http\Requests\Api\UpdateProcessStepRequest;
use App\Models\LotTransformation;
use App\Models\ProcessStep;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;

class ProcessStepController extends Controller
{
    public function index(PaginatedIndexRequest $request): LengthAwarePaginator
    {
        return ProcessStep::query()
            ->orderBy('sequence')
            ->orderBy('id')
            ->paginate($request->perPage());
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
        // Reject deletion if process step is used in any transformations.
        $usedInTransformations = LotTransformation::query()
            ->where('process_step_id', $processStep->id)
            ->exists();

        if ($usedInTransformations) {
            return response()->json([
                'message' => 'Cannot delete process step that is used in transformations.',
            ], 422);
        }

        $processStep->delete();

        return response()->json([
            'message' => 'Process step deleted successfully',
        ], 200);
    }
}
