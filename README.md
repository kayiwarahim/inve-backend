# Inventory Management System - Backend API

Laravel 13 REST API for multi-branch poultry inventory operations, with source-lot intake, batch/barcode tracking, stock movements, lot transformations, traceability, audit logs, and role/permission access control.

## Table of Contents

- [Overview](#overview)
- [Current Functional Scope](#current-functional-scope)
- [Current Progress](#current-progress)
- [Tech Stack](#tech-stack)
- [Requirements](#requirements)
- [Setup](#setup)
- [Development Commands](#development-commands)
- [Authentication & Authorization](#authentication--authorization)
- [API Endpoints (Current)](#api-endpoints-current)
- [Pagination & Report Filters](#pagination--report-filters)
- [Core Data Model](#core-data-model)
- [Testing](#testing)

## Overview

- Framework: Laravel 13
- Auth: Laravel Sanctum cookie/session authentication, with restricted tokens for MFA enrollment
- RBAC: Spatie Laravel Permission
- Timezone: `Africa/Kampala`
- Typical flow: Source Lot Intake -> Batch/Barcode -> Stock Movements & Transformations -> Traceability & Reporting

## Current Functional Scope

- User auth: register, cookie-based login, logout, logout from all devices, profile update, password change
- MFA: authenticator setup and confirmation, login challenges, single-use recovery codes, and role-based enrollment requirements
- OTP password reset flow (request, verify, reset)
- User, role, and permission management (RBAC)
- Master data management:
    - Branches
    - Warehouses
    - Suppliers
    - Products (with category validation for input/output workflows)
    - Process steps
- Batch lifecycle:
    - Batch CRUD
    - Barcode generation and lookup
    - Barcode printing
- Source-lot intake:
    - Captures receiving metrics (`gross_car_weight_kg`, tray count/weight, net received weight)
    - Auto-creates root batch, stock entry, and stock transaction
- Inventory operations:
    - Stock in
    - Stock out
    - Warehouse-to-warehouse transfer
- Lot transformations:
    - Input/output/byproduct/waste lines
    - Mass-balance validation
    - Input vs output warehouse flow
    - Mixed portions replenishment endpoint
- Traceability:
    - Trace a code back through transformation history to related source lots
- Reporting:
    - Stock levels and scan lookups
    - Excel stock level export for current stock, a day, or a date range, with branch/warehouse filters
    - Dashboard product stock, movement history, and recent transfers
    - Audit log listing with pagination/filtering

## Current Progress

Reviewed against the repository on October 2, 2026.

- The API implements the core inventory workflow: intake, batch/barcode management, stock movements, transformations, mixed-portion repacking, and multi-source traceability.
- Authentication now uses first-party sessions with CSRF protection. MFA includes expiring challenges, replay protection, hashed recovery codes, and mandatory enrollment for configured roles.
- List endpoints support validated pagination, and batch search returns live stock locations. Dashboard product totals and period stock exports have dedicated feature tests.
- Audit storage has migrated from `audit_logs` to Spatie's `activity_log`; `/api/auditlogs` preserves the existing response field names.
- Movement approval and stock snapshot tables exist, but no dedicated approval or snapshot API routes are currently exposed. The console routes do not define scheduled inventory tasks.

These items describe implemented code and existing test coverage; they are not a production-readiness certification or a report of a fresh test run.

## Tech Stack

### Runtime

- PHP `^8.4` (as required by `composer.json`)
- Laravel `^13.0`

### Main Packages

- `laravel/sanctum` `^4.3`
- `laravel/fortify` `1.40` (MFA support primitives; application-owned MFA endpoints)
- `spatie/laravel-activitylog` `^5.0`
- `spatie/laravel-permission` `^6.9`
- `picqer/php-barcode-generator` `^3.2`
- `barryvdh/laravel-dompdf` `^3.1`
- `maatwebsite/excel` `3.1.*`

### Dev Tooling

- `pestphp/pest` `^4.7`
- `pestphp/pest-plugin-laravel` `^4.1`
- `laravel/pint` `^1.24`
- `laravel/sail` `^1.41`
- `laravel/boost` `^2.4`
- `laravel/pail` `^1.2.2`

## Requirements

- PHP 8.4+
- Composer
- MySQL
- Node.js + npm
- SQLite PHP support for the in-memory test database

## Setup

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
```

Configure the database connection in `.env` and create the configured MySQL database before continuing:

```bash
php artisan migrate --no-interaction
npm run build
```

Optional seed:

```bash
php artisan db:seed --no-interaction
```

The seeder creates permissions/roles, process steps, branches/warehouses, products, suppliers, and a demo administrator with a fixed password. Use these demo records for local development only. `UserSeeder` creates a new user each run, so repeated full seeding can fail on the unique email constraint.

Set `SANCTUM_STATEFUL_DOMAINS` to the frontend hosts (including ports), `CORS_ALLOWED_ORIGINS` to the frontend origins, and `CORS_SUPPORTS_CREDENTIALS=true`. Configure `SESSION_DOMAIN` for the deployment. `.env.example` defaults to database sessions/cache and synchronous queues.

Password reset OTPs use mail. The default `MAIL_MAILER=log` writes mail locally; configure a delivery-capable mailer when email delivery is needed. `MFA_REQUIRED_ROLES` defaults to `admin,manager` in `.env.example`; `MFA_ISSUER` controls the authenticator label.

## Development Commands

```bash
composer run dev      # app server + queue listener + vite
php artisan test --compact # run test suite
vendor/bin/pint --dirty --format agent # format changed PHP files
php artisan queue:work
php artisan pail
```

## Authentication & Authorization

- Public auth/OTP endpoints are available without token and are rate limited.
- Public registration creates an account without assigning privileged roles; grant access through the RBAC user management flow or seeders.
- All other endpoints are under `auth:sanctum` and the application's `mfa` middleware.
- Most authenticated endpoints also require permission middleware (for example `permission:products.view,api`).

### Frontend Login Flow

1. Request `GET /sanctum/csrf-cookie` with credentials enabled from a configured frontend origin. This endpoint is outside the `/api` prefix.
2. Submit `POST /api/login` (or registration) with cookies and the decoded `XSRF-TOKEN` cookie in the `X-XSRF-TOKEN` header. Send `Accept: application/json` and an appropriate `Origin` or `Referer` header.
3. If the response has `mfa_required: true`, submit its `challenge_token` and either an authenticator `code` or `recovery_code` to `POST /api/mfa/challenge`.
4. If `mfa_enrollment_required: true`, use the returned short-lived bearer token for MFA status/setup/confirmation. It cannot access inventory endpoints. Setup requires the current password; confirmation requires the authenticator code.
5. After MFA confirmation, save the returned recovery codes and log in again. Normal completed login establishes a session cookie and does not return a general API token.

Challenges expire after five minutes; enrollment expires after ten minutes. MFA setup returns the secret, provisioning URI, and QR SVG. Recovery-code regeneration and MFA removal require the current password and a factor; configured mandatory roles cannot disable MFA. MFA changes revoke authentication and require another login. `POST /logout-all` revokes all sessions and tokens.

## API Endpoints (Current)

Base prefix: `/api`

### Public

- `POST /register`
- `POST /login`
- `POST /mfa/challenge`
- `POST /password/request-otp`
- `POST /password/verify-otp`
- `POST /password/reset`

### Authenticated Profile

- `POST /logout`
- `POST /logout-all`
- `GET /me`
- `PUT /profile/update`
- `POST /profile/change-password`

### MFA Management

- `GET /mfa/status`
- `POST /mfa/setup`
- `POST /mfa/confirm`
- `POST /mfa/recovery-codes/regenerate`
- `DELETE /mfa`

### RBAC & Users

- Users: `GET/POST /users`, `GET/PUT/DELETE /users/{user}`
- User access meta: `GET /users/access-meta`
- User roles/permissions:
    - `PUT /users/{user}/role`
    - `PUT /users/{user}/roles`
    - `PUT /users/{user}/permissions`
    - `DELETE /users/{user}/permissions`
- Roles: `GET/POST /roles`, `GET/PUT/DELETE /roles/{role}`
- Permissions: `GET/POST /permissions`, `GET/PUT/DELETE /permissions/{permission}`

### Master Data

- Products: `GET/POST /products`, `GET/PUT/DELETE /products/{product}`
- Warehouses: `GET/POST /warehouses`, `GET/PUT/DELETE /warehouses/{warehouse}`
- Branches: `GET/POST /branches`, `GET/PUT/DELETE /branches/{branch}`
- Suppliers: `GET/POST /suppliers`, `GET/PUT/DELETE /suppliers/{supplier}`
- Process Steps: `GET/POST /process-steps`, `GET/PUT/DELETE /process-steps/{processStep}`

### Batches, Barcodes, Search, Scan

- Batches: `GET/POST /batches`, `GET/PUT/DELETE /batches/{batch_code}`
- Batch barcode print endpoints:
    - `GET /batches/{id}/print-barcode`
    - `POST /batches/{batch}/print`
- Search: `GET /search/{code}`
- Barcodes:
    - `GET/POST /barcodes`
    - `GET /barcodes/code/{code}`
    - `GET/PUT/DELETE /barcodes/{barcode}`
- Generic scan: `GET /scan/{code}`

### Source Lots, Transformations, Traceability

- Source lots:
    - `GET /source-lots`
    - `POST /source-lots`
    - `GET /source-lots/{sourceLot}`
- Lot transformations:
    - `GET /lot-transformations`
    - `POST /lot-transformations`
    - `POST /lot-transformations/repack-mixed`
- Traceability: `GET /trace/{code}`

### Stock & Reporting

- Stock movements:
    - `POST /stock/in/{batch_code}`
    - `POST /stock/out/{batch_code}`
    - `POST /stock/transfer/{batch_code}`
- Stock levels:
    - `GET /stock-levels`
    - `GET /stock-levels/export`
    - `GET /stock-levels/warehouse/{warehouseId}/batch/{batchId}`
    - `GET /stock-levels/scan/{barcode}`
- Dashboard:
    - `GET /dashboard/product-stock/{product_id}`
    - `GET /dashboard/product-stock`
    - `GET /dashboard/recent-transfers`
    - `GET /dashboard/movement-history`
    - `GET /dashboard/warehouse-products/{warehouse_id}`
- Audit logs: `GET /auditlogs`

## Pagination & Report Filters

- Endpoints using `PaginatedIndexRequest` accept `page` and `per_page`; allowed page sizes are `10`, `20`, `50`, and `100`, with a default of `10`.
- Roles and permissions retain their collection response when `per_page` is omitted, and return pagination when it is supplied.
- Stock levels accept `warehouse_id` and `batch_id` filters.
- Stock level exports accept `warehouse_id`, `branch_id`, and either `day` or the pair `start_date`/`end_date`, using `YYYY-MM-DD`. A day cannot be combined with a range. Period exports include opening, incoming, outgoing, and closing quantities.
- Audit logs accept `user_id`, `action`, `search`, `from`, and `to`, alongside pagination.

## Core Data Model

Main tables currently in use:

- `users`
- `sessions`, `cache`, `cache_locks`, `jobs`, `job_batches`, `failed_jobs`
- `personal_access_tokens`
- `roles`, `permissions`, `model_has_roles`, `model_has_permissions`, `role_has_permissions`
- `branches`
- `warehouses`
- `suppliers`
- `products`
- `source_lots`
- `process_steps`
- `batches`
- `barcodes`
- `barcode_print_logs`
- `batch_stocks`
- `stock_transactions`
- `stock_movements`
- `stock_snapshots`
- `stocks`, `movement_approvals`
- `lot_transformations`
- `lot_transformation_lines`
- `activity_log` (replaces the legacy `audit_logs` table through migration)
- `password_otps`

## Testing

The Pest 4 suite includes feature coverage for cookie authentication/CSRF, auth/RBAC, MFA enrollment/challenges/recovery/revocation, barcode print logging, inventory validation and deletion guards, source-lot intake, transformation warehouse/category rules, mixed-source traceability, pagination, batch search locations, dashboard totals, stock exports, and audit filters. Unit tests include TOTP behavior.

`phpunit.xml` configures an in-memory SQLite database, array sessions/cache/mail, and synchronous queues for tests.

Run all tests:

```bash
php artisan test --compact
```

Run a single test file:

```bash
php artisan test --compact --filter=SourceLotIntakeTest
```
