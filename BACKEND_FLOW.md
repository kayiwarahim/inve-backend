# Backend Flow & Progress Map

## Snapshot
- Framework: Laravel 12 API backend.
- Auth: Laravel Sanctum token auth (`auth:sanctum`).
- Access control: Spatie roles/permissions on `api` guard.
- Core domain: Inventory by branch, warehouse, product, batch, barcode, and stock movement.
- Current architecture has **two parallel stock systems**:
  - Legacy path: `batch_stocks` + `stock_transactions` (actively used by API controllers).
  - Newer path: `stocks` + `stock_movements` + `movement_approvals` + `stock_snapshots` (implemented in services, partially exposed).

## High-Level Request Flow
1. Request enters `routes/api.php`.
2. Route passes through middleware:
- `auth:sanctum` for authenticated endpoints.
- `permission:* ,api` for feature-level authorization.
3. Controller validates input (inline `Request::validate()` or FormRequest for users).
4. Controller either:
- uses direct Eloquent/DB operations (majority of endpoints), or
- delegates to service classes (approval and stock-level reads).
5. JSON response returned (or PDF stream for barcode print endpoint).

## Auth & Access Flow
### Public endpoints
- `POST /api/register`
- `POST /api/login`
- OTP reset flow:
  - `POST /api/password/request-otp`
  - `POST /api/password/verify-otp`
  - `POST /api/password/reset`
- `GET /api/test-mail`

### Authenticated endpoints
- Protected with `auth:sanctum`.
- Authorization is permission-based (`permission:<name>,api`).
- `GET /api/me` returns user with direct + effective permissions.
- Login token behavior:
  - max 3 active tokens per user (oldest removed when exceeded),
  - token expires in 7 days.

### Role/Permission setup
Seeded permissions include:
- Product: `products.view/create/edit/delete`
- Batch: `batches.view/create/edit/delete`
- Warehouse: `warehouses.view/create/edit/delete`
- Branch: `branches.view/create/edit/delete`
- Stock: `stock.in/out/transfer/reconcile`
- Reports: `reports.view/export`
- Users: `users.view/manage`

Seeded roles:
- `admin`
- `warehouse manager`
- `warehouse worker`
- `manager`

## Feature Flows

## 1) User Management
Controller: `App\Http\Controllers\Api\UserController`

- List/show users with roles and permissions.
- Create/update users with `StoreUserRequest` and `UpdateUserRequest`.
- Manage roles/permissions:
  - assign single role,
  - sync multiple roles,
  - sync permissions,
  - revoke all direct permissions.
- Access metadata endpoint provides valid `api` roles/permissions for frontend dropdowns.

Flow:
- Validate request via FormRequest.
- Persist user fields.
- Sync roles/permissions if provided.
- Return normalized user payload including `effective_permissions`.

## 2) Product Management
Controller: `Api\ProductController`

- CRUD for products (`sku`, `name`, `unit`, `description`).
- Uses inline validation and direct Eloquent operations.

## 3) Branch & Warehouse Management
Controllers:
- `Api\BranchController`
- `Api\WarehouseController`

- Full CRUD for branches and warehouses.
- Warehouses are linked to branches.

## 4) Batch + Barcode Creation
Controller: `Api\BatchController`
Service: `App\Services\BarcodeService`

`POST /api/batches` flow:
1. Create batch with generated batch code (`B-YYYYMMDD-His`).
2. Create initial `batch_stocks` record for warehouse quantity.
3. Create `stock_transactions` initial `in` entry.
4. Generate barcode image (Code 128), store under public disk.
5. Create `barcodes` row and return metadata + image URL.

Other batch capabilities:
- `GET /api/batches/{batch_code}`
- `PUT /api/batches/{batch_code}`
- `DELETE /api/batches/{batch_code}`
- `GET /api/search/{code}` searches by batch code or barcode code and returns location/history summary.
- `GET /api/batches/{id}/print-barcode` returns PDF stream.

## 5) Stock Movements (Legacy Operational Path)
Controller: `Api\StockMovementController`
Tables: `batch_stocks`, `stock_transactions`

### Stock In
- Increments `batch_stocks.quantity` for target warehouse.
- Creates `stock_transactions` type `in`.

### Stock Out
- Validates sufficient quantity.
- Decrements source warehouse stock.
- Deletes stock row if quantity reaches 0.
- Creates `stock_transactions` type `out`.

### Transfer
- Validates source quantity.
- Decrements source stock, increments target stock.
- Creates `stock_transactions` type `transfer`.

All 3 are wrapped in DB transactions in controller.

## 6) Approval Workflow (New Service Path)
Controller: `Api\MovementApprovalController`
Service: `App\Services\MovementApprovalService`

- `POST /api/movement-approvals/{id}/approve`
- `POST /api/movement-approvals/{id}/reject`

Approval service behavior:
- Approve: lock approval, set status `APPROVED`, set approver/timestamp, mark movement approved, apply stock changes via `StockLevelService`.
- Reject: set `REJECTED`, store remarks, mark movement rejected.

Important: This workflow applies to `stock_movements` records (new system), not legacy `stock_transactions`.

## 7) Stock Levels & Scan
Controllers:
- `Api\StockLevelController`
- `Api\ScanController`

`StockLevelController`:
- `GET /api/stock-levels` reads from `stocks` table.
- `GET /api/stock-levels/warehouse/{warehouseId}/batch/{batchId}` uses `StockLevelService::getWarehouseBatchStock()`.
- `GET /api/stock-levels/scan/{barcode}` resolves barcode and returns positive-quantity `stocks` locations.

`ScanController`:
- `GET /api/scan/{code}` resolves barcode and returns live locations from `stock_snapshots` table.

## 8) Dashboard / Reporting
Controller: `Api\DashboardController`

Endpoints:
- `GET /api/dashboard/product-stock/{product_id}`
- `GET /api/dashboard/product-stock`
- `GET /api/dashboard/recent-transfers`
- `GET /api/dashboard/warehouse-products/{warehouse_id}`

Data source is mostly legacy (`batch_stocks` + `stock_transactions`) with joins to products/warehouses/branches.

## Service Layer (What Exists)
- `BarcodeService`: creates barcode images + barcode records.
- `BarcodePrintService`: logs print operations.
- `StockMovementService`: designed to create `stock_movements`, enforce approval threshold (>100 for OUT/TRANSFER), and call stock-level application logic.
- `StockLevelService`: source-of-truth API for `stocks` table updates + snapshots.
- `MovementApprovalService`: approval/rejection operations and stock application.

## Database Domain Map
Core master tables:
- `users`
- `branches`
- `warehouses`
- `products`
- `batches`
- `barcodes`

Legacy operational tables (active in controllers):
- `batch_stocks`
- `stock_transactions`

New operational tables (active in services / stock level endpoints):
- `stocks`
- `stock_movements`
- `movement_approvals`
- `stock_snapshots`

Support tables:
- `password_otps`
- `barcode_print_logs`
- `audit_logs`
- Sanctum and Spatie permission tables

## Current Progress (Implemented)
- Authentication + Sanctum token flow is implemented.
- Role/permission authorization is implemented on most API routes.
- CRUD exists for users, products, branches, warehouses, batches.
- Barcode generation and barcode PDF print endpoint exist.
- Legacy stock operations (in/out/transfer) are working through API controllers.
- Dashboard reporting endpoints are implemented.
- New stock service architecture (movement/approval/snapshot) exists and is partially wired.

## Current Gaps / Risks To Address Before Major Changes
1. Dual stock engines are running in the same codebase (`batch_stocks` vs `stocks`) and can diverge.
2. Barcode API routing points to `Inventory\BarcodeController`, but that controller does not implement all resource methods listed in routes (`index/show/update/destroy`) and route model binding names are conflicting (`{barcode}` and `{code}` on same prefix).
3. `BarcodePrintLog` model expects `barcode_id`, but migration uses `batch_id`; relation/model schema mismatch exists.
4. `Inventory\MovementApprovalController` references `App\Services\Inventory\MovementApprovalService`, which is not present; likely dead/legacy file.
5. `StockMovementService` (new path) is not used by `Api\StockMovementController` (legacy path), so approvals threshold logic is bypassed in current API movement operations.
6. Minimal automated tests in `tests/`; feature coverage for critical flows is currently missing.

## Suggested Change Strategy (Safe Next Step)
1. Pick one inventory source of truth (`stocks` or `batch_stocks`) and align all write/read paths.
2. Fix barcode controller routing mismatch first to avoid endpoint failures.
3. Align `barcode_print_logs` model and migration columns.
4. Move stock movement endpoints onto `StockMovementService` if approval workflow is required.
5. Add Pest feature tests for auth, movements, approval, and scan endpoints before further refactors.

## Quick Route Inventory
The project currently exposes 60 API routes (`php artisan route:list --path=api --except-vendor`).
