# Inventory Management System - Backend API

Laravel 12 REST API for multi-branch poultry inventory operations, with source-lot intake, batch/barcode tracking, stock movements, lot transformations, traceability, audit logs, and role/permission access control.

## Table of Contents

- [Overview](#overview)
- [Current Functional Scope](#current-functional-scope)
- [Tech Stack](#tech-stack)
- [Requirements](#requirements)
- [Setup](#setup)
- [Development Commands](#development-commands)
- [Authentication & Authorization](#authentication--authorization)
- [API Endpoints (Current)](#api-endpoints-current)
- [Core Data Model](#core-data-model)
- [Testing](#testing)

## Overview

- Framework: Laravel 12
- Auth: Laravel Sanctum tokens
- RBAC: Spatie Laravel Permission
- Timezone: `Africa/Kampala`
- Typical flow: Source Lot Intake -> Batch/Barcode -> Stock Movements & Transformations -> Traceability & Reporting

## Current Functional Scope

- User auth: register, login, logout, profile update, password change
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
  - Stock level export
  - Dashboard product stock, movement history, and recent transfers
  - Audit log listing with pagination/filtering

## Tech Stack

### Runtime
- PHP `^8.2`
- Laravel `^12.0`

### Main Packages
- `laravel/sanctum` `^4.3`
- `spatie/laravel-permission` `^6.9`
- `picqer/php-barcode-generator` `^3.2`
- `barryvdh/laravel-dompdf` `^3.1`
- `maatwebsite/excel` `3.1.*`

### Dev Tooling
- `pestphp/pest` `^3.0`
- `pestphp/pest-plugin-laravel` `^3.0`
- `laravel/pint` `^1.24`
- `laravel/sail` `^1.41`
- `laravel/boost` `^2.1`
- `laravel/pail` `^1.2.2`

## Requirements

- PHP 8.2+
- Composer
- MySQL
- Node.js + npm

## Setup

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan migrate
npm run build
```

Optional seed:

```bash
php artisan db:seed
```

## Development Commands

```bash
composer run dev      # app server + queue listener + vite
php artisan test      # run test suite
vendor/bin/pint       # format code
php artisan queue:work
php artisan pail
```

## Authentication & Authorization

- Public auth/OTP endpoints are available without token.
- All other endpoints are under `auth:sanctum`.
- Most authenticated endpoints also require permission middleware (for example `permission:products.view,api`).

## API Endpoints (Current)

Base prefix: `/api`

### Public

- `POST /register`
- `POST /login`
- `POST /password/request-otp`
- `POST /password/verify-otp`
- `POST /password/reset`

### Authenticated Profile

- `POST /logout`
- `GET /me`
- `PUT /profile/update`
- `POST /profile/change-password`

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

## Core Data Model

Main tables currently in use:

- `users`
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
- `lot_transformations`
- `lot_transformation_lines`
- `audit_logs`
- `password_otps`

## Testing

Run all tests:

```bash
php artisan test --compact
```

Run a single test file:

```bash
php artisan test --compact --filter=SourceLotIntakeTest
```
