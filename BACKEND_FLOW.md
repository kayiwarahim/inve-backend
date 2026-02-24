# Backend Flow & Progress Map

## Snapshot
- Framework: Laravel 12 API backend.
- Auth: Laravel Sanctum token auth (`auth:sanctum`).
- Access control: Spatie roles/permissions on `api` guard.
- Core domain: Inventory by branch, warehouse, product, batch, barcode, and stock movement.
- Active architecture is now the legacy operational path:
  - `batch_stocks` + `stock_transactions` are the source of truth for stock state and movement history.

## High-Level Request Flow
1. Request enters `routes/api.php`.
2. Route passes through middleware:
- `auth:sanctum` for authenticated endpoints.
- `permission:* ,api` for feature-level authorization.
3. Controller validates input (inline `Request::validate()` or FormRequest for users).
4. Controller handles validation and business logic using Eloquent + DB transactions.
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

## 6) Stock Levels & Scan
Controllers:
- `Api\StockLevelController`
- `Api\ScanController`

`StockLevelController`:
- `GET /api/stock-levels` reads from `batch_stocks`.
- `GET /api/stock-levels/warehouse/{warehouseId}/batch/{batchId}` reads specific quantity from `batch_stocks`.
- `GET /api/stock-levels/scan/{barcode}` resolves barcode and returns positive-quantity `batch_stocks` locations.

`ScanController`:
- `GET /api/scan/{code}` resolves barcode and returns live locations from `batch_stocks`.

## 7) Dashboard / Reporting
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
- Stock-level and scan endpoints are aligned to legacy stock tables.
- Dashboard reporting endpoints are implemented.

## Current Gaps / Risks To Address Before Major Changes
1. `BarcodePrintLog` model expects `barcode_id`, but migration uses `batch_id`; relation/model schema mismatch exists.
2. Minimal automated tests in `tests/`; feature coverage for critical flows is currently missing.

## Suggested Change Strategy (Safe Next Step)
1. Align `barcode_print_logs` model and migration columns.
2. Add Pest feature tests for auth, stock movements, and scan endpoints before further refactors.

## Quick Route Inventory
The project currently exposes 56 API routes (`php artisan route:list --path=api --except-vendor`).
