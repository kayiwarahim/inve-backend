# Frontend Guide: Stock Transformation + Product Category Changes

This guide explains the backend changes you should implement in frontend forms and API calls.

## 1. Warehouse Flow Change (Old vs New)

Old transformation flow used one warehouse:
- `warehouse_id` for both consuming input and posting output.

New transformation flow supports split warehouses:
- `input_warehouse_id` for stock consumption
- `output_warehouse_id` for stock posting

Backward compatibility:
- `warehouse_id` still works as fallback for both.

## 2. Product Category Model (New)

Products now have a `category` column with these values:
- `input` (can only be used as transformation input)
- `output` (can only be used as transformation output/byproduct/waste)
- `both` (can be used as either input or output)

Examples:
- Live chicken: `input`
- Waste products: `output`
- Whole chicken (1.1 - 1.7 etc): `both`

## 3. Product API Filtering for UI Selectors

Use these endpoints to power dropdowns:

- Input selector:
  - `GET /api/products?usage=input`
  - returns products where `category` is `input` or `both`

- Output selector:
  - `GET /api/products?usage=output`
  - returns products where `category` is `output` or `both`

- Optional:
  - `GET /api/products?usage=both` returns only `both`
  - `GET /api/products` returns all

When creating/updating products, include:
- `category` in `input|output|both`

## 4. Transformation Request Contract

## `POST /api/lot-transformations`

Preferred payload now:
```json
{
  "process_step_id": 1,
  "branch_id": 1,
  "input_warehouse_id": 2,
  "output_warehouse_id": 3,
  "transformed_at": "2026-02-26 10:00:00",
  "inputs": [
    { "batch_id": 10, "weight_kg": 5.0 }
  ],
  "outputs": [
    { "product_id": 6, "weight_kg": 5.0 }
  ]
}
```

## `POST /api/lot-transformations/repack-mixed`

Also supports:
- `input_warehouse_id`
- `output_warehouse_id`

## 5. Backend Enforcement You Must Handle

The API now returns `422` when:
- selected input warehouse is not in the selected branch
- selected output warehouse is not in the selected branch
- selected input warehouse has insufficient stock for an input batch
- input product is not allowed in input flow
- output/byproduct/waste product is not allowed in output flow
- mass balance fails

Common stock error message:
- `Insufficient stock for one or more input lots.`

Category error messages:
- `Selected product is not allowed as transformation input.`
- `Selected product is not allowed as transformation output.`

## 6. Distinct Outputs and Traceability

Existing logic remains active:
- If an output line has no `batch_id`, backend creates a new batch with unique `batch_code`.
- Backend generates barcode for the new batch.
- Output stock is registered in `output_warehouse_id`.

Traceability now includes:
- `input_warehouse`
- `output_warehouse`

## 7. Frontend Implementation Checklist

1. Replace single warehouse control with input and output warehouse controls.
2. Load input products from `GET /api/products?usage=input`.
3. Load output products from `GET /api/products?usage=output`.
4. Ensure batch selection for input lines reflects available stock in selected input warehouse.
5. Handle new 422 validation messages in form errors.
6. Display input and output warehouse in traceability/timeline UI.
