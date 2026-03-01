# Track-Flow Backend Software Architecture (As-Built)

## 1. Document Purpose

This document describes the current backend architecture of the `inve-backend` system, including:

- system modules and responsibilities
- operational flows (how key business processes execute)
- API surface by domain
- database design (tables, relationships, and data flow)
- security and access control model
- known implementation gaps and technical risks

The content is based on the current codebase state in this repository as of **March 1, 2026**.

---

## 2. System Overview

Track-Flow backend is a Laravel 12 REST API for poultry/inventory traceability and warehouse operations.

### 2.1 Core Business Capabilities

- user authentication and profile management
- role/permission-based authorization (Spatie Permission)
- branch and warehouse management
- product and batch lifecycle
- source lot intake and root-batch creation
- stock movement operations (`in`, `out`, `transfer`)
- lot transformation workflow (input -> output/byproduct/waste)
- barcode generation, lookup, scanning, and print logging
- traceability graph (batch lineage + source lot ancestry)
- dashboard reporting for stock and movement history

### 2.2 Technology Stack

- Framework: Laravel 12
- Language: PHP 8.2+
- Auth: Laravel Sanctum (token-based API usage)
- Authorization: `spatie/laravel-permission`
- Barcode: `picqer/php-barcode-generator`
- PDF: `barryvdh/laravel-dompdf`
- DB: MySQL (configured via `.env`)
- Queue: configured as `sync` by default

---

## 3. High-Level Architecture

## 3.1 Layered Structure

1. **HTTP/API Layer**
   - Controllers in `app/Http/Controllers/Api`
   - Routes in `routes/api.php`
   - Middleware aliases set in `bootstrap/app.php`

2. **Validation Layer**
   - Form Requests for newer/critical flows (users, suppliers, source lots, transformations, roles/permissions/process steps)
   - Some controllers still use inline validation

3. **Domain/Business Layer**
   - Logic primarily inside controllers
   - Shared services: `BarcodeService`, `BarcodePrintService`

4. **Persistence Layer**
   - Eloquent models + MySQL schema migrations
   - Transactional writes for critical operations (`stock`, `transformation`, `source lot intake`)

5. **Integration Layer**
   - Mail queue for OTP delivery
   - Filesystem for barcode PNG storage (`storage/app/public/barcodes`)

## 3.2 Request Lifecycle (Typical Protected API Call)

1. request hits `/api/...`
2. `auth:sanctum` authenticates bearer token
3. Spatie permission middleware checks permission string
4. controller validates payload
5. controller uses Eloquent + transaction for write integrity
6. JSON response returns normalized operation result

---

## 4. Module Architecture and Responsibilities

## 4.1 Identity and Access Module

### Components

- `AuthController`
- `UserController`
- `RoleController`
- `PermissionController`
- `PasswordOtpController`
- `User` model + Sanctum tokens + Spatie permission tables

### Responsibilities

- account registration/login/logout
- password change and profile update
- password reset with OTP email
- CRUD for users
- role and permission CRUD
- role assignment and direct permission sync on users

### Authorization Model

- Guard: `api`
- Permissions enforced per endpoint using middleware:
  - example: `permission:users.manage,api`
- User payload returns:
  - `roles`
  - `direct_permissions`
  - effective `permissions` (role + direct)
  - `effective_permissions` names

---

## 4.2 Master Data Module

### Components

- `BranchController`
- `WarehouseController`
- `ProductController`
- `SupplierController`
- `ProcessStepController`

### Responsibilities

- maintain organizational topology (`branches`, `warehouses`)
- maintain product catalog with category semantics (`input`, `output`, `both`)
- maintain supplier records for source-lot traceability
- maintain manufacturing process-step catalog and sequence

---

## 4.3 Inventory and Batch Module

### Components

- `BatchController`
- `StockMovementController`
- `StockLevelController`
- `DashboardController`
- Models: `Batch`, `BatchStock`, `StockTransaction`

### Responsibilities

- create/manage batches and their initial stock
- stock-in, stock-out, and warehouse transfer operations
- read current stock levels by batch+warehouse
- aggregate and report product stock by warehouse/branch
- provide movement history with transfer/process semantics

---

## 4.4 Transformation and Traceability Module

### Components

- `SourceLotController`
- `LotTransformationController`
- `TraceabilityController`
- Models: `SourceLot`, `LotTransformation`, `LotTransformationLine`

### Responsibilities

- ingest source lots and generate root traceable batches
- transform input batches into outputs/byproducts/waste
- enforce mass-balance and product category constraints
- track full upstream lineage from finished batch to source lots

---

## 4.5 Barcode Module

### Components

- `BarcodeController`
- `ScanController`
- `BarcodeService`
- `BarcodePrintController`
- `BarcodePrintService`

### Responsibilities

- generate unique barcode per batch
- lookup batch/product by barcode
- scan for real-time storage locations
- generate printable PDF and log print events

---

## 5. Operational Flows (How Operations Work)

## 5.1 Login and Token Flow

1. client calls `POST /api/login` with email/password
2. backend verifies credentials
3. old token may be trimmed (max ~3 active tokens behavior)
4. Sanctum token is generated with `['*']` abilities and expiry
5. response returns token + user access envelope

Notes:
- current API style is token-based (not SPA cookie/CSRF flow)
- API middleware no longer prepends Sanctum stateful middleware

## 5.2 Source Lot Intake Flow

1. `POST /api/source-lots`
2. validate branch-warehouse consistency
3. validate product category allows input
4. DB transaction:
   - create `source_lots` record
   - create root `batches` record linked to source lot
   - create `batch_stocks` entry in selected warehouse
   - create `stock_transactions` entry (`type=in`)
   - generate barcode for the new batch
5. response returns source lot + batch + barcode

## 5.3 Direct Batch Creation Flow

1. `POST /api/batches`
2. create batch
3. create initial `batch_stocks`
4. create initial `stock_transactions` (`in`, reason `Initial receipt`)
5. generate barcode

## 5.4 Stock Transfer Flow

1. `POST /api/stock/transfer/{batch_code}`
2. load source stock row by `from_warehouse_id`
3. validate quantity sufficiency
4. decrement source stock
5. delete source stock row when quantity <= 0
6. increment destination stock (create row if absent)
7. insert `stock_transactions` as `type=transfer`

## 5.5 Stock Out Flow

1. `POST /api/stock/out/{batch_code}`
2. find `batch_stocks` row in warehouse
3. validate available quantity
4. decrement quantity
5. delete row when depleted
6. log `stock_transactions` as `type=out`

## 5.6 Lot Transformation Flow

1. `POST /api/lot-transformations` or `/api/lot-transformations/repack-mixed`
2. validate:
   - warehouse branch ownership
   - mass-balance tolerance (`<= 0.02kg` drift)
   - input/output product category compatibility
3. DB transaction:
   - create transformation header
   - consume input stock rows (with `lockForUpdate`)
   - delete depleted stocks
   - create output/byproduct batches when needed
   - increment destination stocks
   - generate barcodes for newly materialized batches
   - write line-level transformation rows (`input/output/byproduct/waste`)
   - write stock transactions (`out` for inputs, `in` for outputs)
4. return fully expanded transformation resource

## 5.7 Traceability Flow

1. `GET /api/trace/{code}` resolves batch by barcode or batch code
2. recursively traverses producing transformation lines
3. builds lineage nodes including:
   - process step metadata
   - transformation timestamps
   - input contribution percentages
   - upstream nested ancestry
4. emits current locations, movement history, full lineage tree, and unique source lots

---

## 6. API Surface by Domain

## 6.1 Public Endpoints

- `POST /api/register`
- `POST /api/login`
- `POST /api/password/request-otp`
- `POST /api/password/verify-otp`
- `POST /api/password/reset`

## 6.2 Protected Domains (under `auth:sanctum`)

- auth profile: `/api/me`, `/api/logout`, profile/password update
- users: `/api/users*`
- roles: `/api/roles*`
- permissions: `/api/permissions*`
- master data: `/api/products*`, `/api/branches*`, `/api/warehouses*`, `/api/suppliers*`, `/api/process-steps*`
- batch + barcode: `/api/batches*`, `/api/barcodes*`, `/api/scan/{code}`, `/api/search/{code}`
- source lots + transformations: `/api/source-lots*`, `/api/lot-transformations*`
- traceability: `/api/trace/{code}`
- stock operations: `/api/stock/in|out|transfer/{batch_code}`
- stock/read/reporting: `/api/stock-levels*`, `/api/dashboard/*`

---

## 7. Database Design

## 7.1 Core Entity Relationship Summary

- **Branch 1..n Warehouse**
- **Branch 1..n SourceLot**
- **Supplier 1..n SourceLot**
- **Product 1..n Batch**
- **SourceLot 1..n Batch** (optional on batch)
- **Batch 1..1 Barcode** (logical; unique code)
- **Batch 1..n BatchStock**
- **Warehouse 1..n BatchStock**
- **Batch 1..n StockTransaction**
- **ProcessStep 1..n LotTransformation**
- **LotTransformation 1..n LotTransformationLine**
- **Batch 1..n LotTransformationLine** (optional by line type)
- **SourceLot 1..n LotTransformationLine** (optional)
- **Product 1..n LotTransformationLine** (optional)
- **User n..m Role** (Spatie pivots)
- **User n..m Permission** (Spatie pivots)
- **Role n..m Permission** (Spatie pivots)

## 7.2 Main Operational Tables

1. `users`
   - identity, login, profile, branch assignment
2. `branches`
   - business sites/logical locations
3. `warehouses`
   - physical stock nodes with type (`storage`, `transit`, `quarantine`)
4. `products`
   - SKU master + `category` for transformation eligibility
5. `suppliers`
   - source-lot suppliers
6. `source_lots`
   - intake records of incoming raw/live material
7. `batches`
   - traceable production or source-intake units
8. `barcodes`
   - unique machine-readable identifiers per batch
9. `batch_stocks`
   - current quantity by `(batch_id, warehouse_id)` with unique composite key
10. `stock_transactions`
   - immutable movement journal (`in`, `out`, `transfer`, `adjustment`)
11. `process_steps`
   - transformation stage catalog and sequence
12. `lot_transformations`
   - transformation event header
13. `lot_transformation_lines`
   - transformation inputs/outputs/byproducts/waste
14. `password_otps`
   - temporary password reset OTP data
15. `personal_access_tokens`
   - Sanctum API tokens

## 7.3 Access-Control Tables (Spatie)

- `roles`
- `permissions`
- `model_has_roles`
- `model_has_permissions`
- `role_has_permissions`

## 7.4 Legacy/Secondary Tables Present

These exist in schema/model but are not primary in current API flow:

- `stocks` (older stock table; superseded by `batch_stocks`)
- `stock_movements` and `movement_approvals` (older movement workflow)
- `stock_snapshots` (historical snapshots; not currently exposed)
- `audit_logs` (generic audit structure; no active centralized writer observed)
- `barcode_print_logs` (actively used by barcode print service)

---

## 8. Security, Auth, and Cross-Origin Design

## 8.1 Authentication

- Sanctum personal access tokens
- protected routes use `auth:sanctum`

## 8.2 Authorization

- Spatie middleware aliases configured in `bootstrap/app.php`
- route-level permission strings control access

## 8.3 CORS

- `config/cors.php` supports configurable allowed origins
- credentials support enabled (`supports_credentials=true`)
- origins sourced from `CORS_ALLOWED_ORIGINS`

## 8.4 Password Reset Security

- OTP hash stored (not plaintext)
- OTP expiry enforced
- OTP invalidated/cleaned on success or expiry

---

## 9. Data Integrity and Consistency Controls

- DB transactions around stock and transformation write sets
- row-level lock (`lockForUpdate`) during transformation input consumption
- unique constraint on `batch_stocks (batch_id, warehouse_id)`
- branch ownership checks for transformation/intake warehouse use
- product category checks:
  - inputs require category in `input|both`
  - outputs require category in `output|both`
- transformation mass-balance check before commit

---

## 10. Reporting and Read Models

Current reporting endpoints are read-optimized aggregations:

- stock by product and warehouse (`/dashboard/product-stock*`)
- movement history with transfer/process filtering
- warehouse inventory summaries
- stock-level queries by batch/warehouse/barcode

Traceability endpoint is a derived hierarchical read model assembled at request time from transformations and batch ancestry.

---

## 11. Known Gaps and Implementation Risks (Current State)

1. **Route permission keys vs seeded permissions mismatch**
   - routes currently use keys such as `roles.view`, `permissions.view`, `stock.view`
   - `RolePermissionSeeder` currently seeds `users.*`, `reports.*`, etc., but not all of these new keys
   - impact: unexpected `403 Forbidden` until permissions are seeded/assigned consistently

2. **Mixed validation style**
   - newer controllers use Form Requests, older ones still use inline validation or minimal checks
   - impact: uneven API validation rigor and error consistency

3. **Dual inventory schema history**
   - both `stocks` and `batch_stocks` ecosystems exist
   - current API primarily uses `batch_stocks` + `stock_transactions`
   - impact: onboarding confusion if not documented clearly

4. **Stock-level endpoint behavior**
   - `/api/stock-levels` currently returns raw rows (including zero-quantity rows) unless filtered at caller level
   - impact: UI may show locations where stock is already depleted

5. **Documentation drift risk**
   - existing `README.md` describes routes/features that differ from current `routes/api.php`

---

## 12. Recommended Documentation/Architecture Artifacts to Add Next

1. Sequence diagrams:
   - source-lot intake
   - transformation
   - transfer
   - traceability query recursion
2. ER diagram (MySQL workbench/dbdiagram)
3. Permission matrix (role -> permission mapping)
4. API contract examples (request/response payload catalog)
5. Data retention policy for tokens, OTPs, logs, and print records

---

## 13. Appendix: Key Code Anchors

- Routing: `routes/api.php`
- Middleware aliases: `bootstrap/app.php`
- Auth controllers: `app/Http/Controllers/Api/AuthController.php`, `UserController.php`
- Transformation domain: `LotTransformationController.php`, `TraceabilityController.php`
- Stock domain: `StockMovementController.php`, `StockLevelController.php`, `DashboardController.php`
- Barcode domain: `BarcodeController.php`, `ScanController.php`, `app/Services/BarcodeService.php`
- RBAC seed baseline: `database/seeders/RolePermissionSeeder.php`

