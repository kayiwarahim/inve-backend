<?php

namespace App\Http\Requests\Api;

use App\Models\Warehouse;
use Illuminate\Foundation\Http\FormRequest;

class StoreSourceLotRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'supplier_id' => ['required', 'integer', 'exists:suppliers,id'],
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
            'gross_car_weight_kg' => ['required', 'numeric', 'gt:0'],
            'chicken_tray_count' => ['required', 'integer', 'gt:0'],
            'tray_unit_weight_kg' => ['required', 'numeric', 'gt:0'],
            'received_weight_kg' => ['required', 'numeric', 'gt:0'],
            'received_at' => ['required', 'date'],
            'warehouse_id' => ['required', 'integer', 'exists:warehouses,id'],
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'vehicle_number' => ['nullable', 'string', 'max:80'],
            'delivery_note_number' => ['nullable', 'string', 'max:120'],
            'remarks' => ['nullable', 'string'],
            'expires_at' => ['nullable', 'date', 'after_or_equal:received_at'],
        ];
    }

    public function after(): array
    {
        return [
            function ($validator) {
                $branchId = $this->input('branch_id');
                $warehouseId = $this->input('warehouse_id');

                if ($branchId !== null && $warehouseId !== null) {
                    $warehouse = Warehouse::query()->find($warehouseId);
                    if (! $warehouse || (int) $warehouse->branch_id !== (int) $branchId) {
                        $validator->errors()->add('warehouse_id', 'The selected warehouse does not belong to the provided branch.');
                    }
                }

                $grossWeight = (float) ($this->input('gross_car_weight_kg') ?? 0);
                $receivedWeight = (float) ($this->input('received_weight_kg') ?? 0);
                $trayCount = (int) ($this->input('chicken_tray_count') ?? 0);
                $trayUnitWeight = (float) ($this->input('tray_unit_weight_kg') ?? 0);

                if ($grossWeight > 0 && $receivedWeight > 0 && $trayCount > 0 && $trayUnitWeight > 0) {
                    $totalTrayWeight = $trayCount * $trayUnitWeight;
                    $minimumGrossWeight = $receivedWeight + $totalTrayWeight;

                    if ($grossWeight < $minimumGrossWeight) {
                        $validator->errors()->add('gross_car_weight_kg', "The gross car weight ({$grossWeight} kg) must cover the received chicken weight ({$receivedWeight} kg) plus total tray weight ({$totalTrayWeight} kg).");
                    }
                }
            },
        ];
    }

    public function messages(): array
    {
        return [
            'gross_car_weight_kg.required' => 'The gross car weight is required.',
            'chicken_tray_count.required' => 'The number of chicken trays is required.',
            'tray_unit_weight_kg.required' => 'The tray unit weight is required.',
        ];
    }
}
