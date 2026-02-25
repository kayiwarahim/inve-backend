# Backend Flow Map (Updated for New Traceability Logic)

## Snapshot
- Framework: Laravel 12 API backend.
- Auth: Sanctum (`auth:sanctum`) + Spatie permission middleware (`permission:...,api`).
- Stock truth: `batch_stocks` (current quantities) + `stock_transactions` (movement history).
- Traceability truth: `source_lots` + `lot_transformations` + `lot_transformation_lines`.
- Operational identity: `source_lot_code`, `batch_code`, `barcode.code`, `transformation_code`.

## Frontend Operational Flow (Login to Sale)
1. Authenticate user.
2. Load master data (branches, warehouses, products, suppliers).
3. Receive live chicken via source lot intake.
4. Post process transformations (split/merge/byproduct/waste).
5. Repack mixed portions when needed.
6. Move stock across warehouses/branches.
7. Sell finished goods via stock out.
8. Use scan/stock-level/trace endpoints for visibility and recall.

## 1) Authentication and Access

### Public endpoints
- `POST /api/register`
- `POST /api/login`
- `POST /api/password/request-otp`
- `POST /api/password/verify-otp`
- `POST /api/password/reset`

### Authenticated endpoints
- `POST /api/logout`
- `GET /api/me`
- All domain endpoints under auth + permissions.

### Login behavior
- If user has 3+ tokens, oldest token is deleted.
- New token expires in 7 days.
- `GET /api/me` returns roles, direct permissions, and `effective_permissions`.

## 2) Master Data APIs Used by Frontend

### Branches
- `GET /api/branches`
- `POST /api/branches`
- `GET /api/branches/{branch}`
- `PUT /api/branches/{branch}`
- `DELETE /api/branches/{branch}`

### Warehouses
- `GET /api/warehouses`
- `POST /api/warehouses`
- `GET /api/warehouses/{warehouse}`
- `PUT /api/warehouses/{warehouse}`
- `DELETE /api/warehouses/{warehouse}`

### Products
- `GET /api/products`
- `POST /api/products`
- `GET /api/products/{product}`
- `PUT /api/products/{product}`
- `DELETE /api/products/{product}`

### Suppliers
- `GET /api/suppliers`
- `POST /api/suppliers`
- `GET /api/suppliers/{supplier}`
- `PUT /api/suppliers/{supplier}`
- `DELETE /api/suppliers/{supplier}`

## 3) Source Lot Intake (Start of Traceability)

Endpoint:
- `POST /api/source-lots`

What it does in one transaction:
1. Creates `source_lots` record (supplier, branch, source code, received kg, metadata).
2. Creates root `batches` record linked to `source_lot_id`.
3. Adds initial `batch_stocks` quantity in intake warehouse.
4. Creates `stock_transactions` type `in`.
5. Generates barcode for root batch.

Key validation:
1. `warehouse_id` must belong to posted `branch_id`.
2. `source_lot_code` must be unique.

Read endpoints:
- `GET /api/source-lots`
- `GET /api/source-lots/{sourceLot}`

## 4) Transformation Engine (Core Processing Logic)

Endpoints:
- `POST /api/lot-transformations`
- `POST /api/lot-transformations/repack-mixed`
- `GET /api/lot-transformations`

### `POST /api/lot-transformations` logic
1. Validates branch/warehouse consistency.
2. Validates mass balance:
- `input_total ~= output_total + byproducts_total + waste_total`
- tolerance: `0.02 kg`.
3. For each input line:
- resolve batch by `batch_id` or `source_lot_id`,
- verify stock exists in the posted warehouse,
- deduct stock and log `stock_transactions` `out`,
- add transformation input line.
4. For output/byproduct lines:
- use existing batch if provided or create new batch,
- add/increment stock and log `stock_transactions` `in`,
- generate barcode if missing,
- add transformation output/byproduct line.
5. Persist optional waste lines as lineage records.

### `POST /api/lot-transformations/repack-mixed` logic
1. Uses process step `MIX_BUILD` (must exist).
2. Computes output automatically:
- `output_kg = sum(inputs.weight_kg) - waste_kg`.
3. Builds a normal transformation payload under the hood.
4. Keeps full ancestry for mixed portions.

## 5) Stock Movement and Selling

Controller: `StockMovementController`

### Stock In
- `POST /api/stock/in/{batch_code}`
- Adds quantity to existing batch in target warehouse.
- Logs `stock_transactions` type `in`.
- Does not create source lot or new batch.

### Stock Out (Finished Goods Sale)
- `POST /api/stock/out/{batch_code}`
- Deducts quantity from selected warehouse.
- Rejects with `422` when insufficient stock.
- Deletes stock row when quantity reaches zero.
- Logs `stock_transactions` type `out`.

### Transfer
- `POST /api/stock/transfer/{batch_code}`
- Moves quantity from one warehouse to another.
- Batch identity is unchanged.
- Logs `stock_transactions` type `transfer`.

## 6) Frontend Visibility and Query Endpoints

### Batch/barcode lookup
- `GET /api/search/{code}` (batch code or barcode code).
- `GET /api/barcodes/code/{code}`.
- `GET /api/scan/{code}`.

### Stock visibility
- `GET /api/stock-levels`
- `GET /api/stock-levels/warehouse/{warehouseId}/batch/{batchId}`
- `GET /api/stock-levels/scan/{barcode}`

### End-to-end traceability
- `GET /api/trace/{code}`

`trace` returns:
1. Current locations.
2. Movement history.
3. Recursive lineage tree through transformations.
4. Deduplicated upstream source lots/suppliers.
5. Contribution percentages for blended inputs.

## 7) Rules Frontend Must Respect

1. Always use `/api/source-lots` for new live intake origin.
2. Use `/api/stock/in/{batch_code}` only for existing batch increments.
3. For transformations, post complete input/output/byproduct/waste lines so mass balance can pass.
4. Use correct warehouse for input stock; insufficient stock causes `422`.
5. For branch operations, keep `warehouse_id` and `branch_id` consistent.

## 8) Typical Error Codes

1. `401`: unauthenticated / invalid token / invalid credentials.
2. `403`: authenticated but missing permission.
3. `404`: missing batch/barcode/source-lot.
4. `422`: validation or business rule failure:
- warehouse/branch mismatch,
- mass balance mismatch,
- insufficient stock,
- product mismatch on selected batch.

## 9) Database Model (Current Logic)

### Master
- `users`
- `branches`
- `warehouses`
- `products`
- `suppliers`
- `process_steps`

### Operational
- `source_lots`
- `batches`
- `barcodes`
- `batch_stocks`
- `stock_transactions`
- `lot_transformations`
- `lot_transformation_lines`

## 10) Frontend-First Route Order
1. `POST /api/login`
2. `GET /api/me`
3. `GET /api/branches`, `GET /api/warehouses`, `GET /api/products`, `GET /api/suppliers`
4. `POST /api/source-lots`
5. `POST /api/lot-transformations` (repeat by process stage)
6. `POST /api/lot-transformations/repack-mixed` (when needed)
7. `POST /api/stock/transfer/{batch_code}` (internal/branch movement)
8. `POST /api/stock/out/{batch_code}` (sale)
9. `GET /api/scan/{code}`, `GET /api/stock-levels`, `GET /api/trace/{code}` for verification/recall
