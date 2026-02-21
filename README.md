# Inventory Management System - Backend API

A comprehensive Laravel-based inventory management system with multi-warehouse support, batch tracking, barcode generation, and approval workflows.

## Table of Contents

- [System Overview](#system-overview)
- [Features](#features)
- [Technology Stack](#technology-stack)
- [Requirements](#requirements)
- [Installation](#installation)
- [Configuration](#configuration)
- [API Documentation](#api-documentation)
- [Database Structure](#database-structure)
- [Development](#development)
- [Testing](#testing)

## System Overview

This is a powerful inventory management backend API built with Laravel 12. It provides complete inventory control across multiple branches and warehouses with advanced features like batch tracking, barcode scanning, stock movement approvals, and real-time stock level monitoring.

**Timezone:** Africa/Kampala  
**Database:** MySQL (`inve_backend`)  
**Authentication:** Laravel Sanctum (Token-based)

## Features

### Core Functionality

✅ **Product Management**
- Product catalog with SKU, units, and descriptions
- Product hierarchy and categorization
- Batch assignment and tracking

✅ **Batch Management**
- Batch-level inventory tracking
- Manufacturing and expiry date management
- Unique batch codes
- Automatic barcode generation

✅ **Multi-Location Support**
- Branch management
- Multiple warehouses per branch
- Warehouse-specific stock levels
- Main/sub-branch designation

✅ **Stock Movement Operations**
- **Stock In**: Receive products (production/purchasing)
- **Stock Out**: Issue products (sales/consumption)
- **Stock Transfer**: Move stock between warehouses
- Movement approval workflow
- Real-time stock level updates

✅ **Barcode System**
- Automatic Code-128 barcode generation
- Barcode scanning for quick lookup
- PDF barcode printing
- Barcode print log tracking

✅ **Approval Workflow**
- Movement approval/rejection system
- Multi-level authorization
- Audit trail for approvals

✅ **Stock Tracking & Analytics**
- Real-time stock levels by warehouse
- Stock transaction history
- Stock snapshots for historical data
- Batch-level stock tracking
- Dashboard with key metrics

✅ **Authentication & Security**
- User registration and login
- Password reset via email OTP
- Token-based API authentication (Sanctum)
- Session management

✅ **Audit Logging**
- Complete audit trail
- User activity tracking
- Change history

## Technology Stack

### Backend Framework
- **Laravel Framework**: 12.x
- **PHP**: 8.2+

### Key Packages
- **Laravel Sanctum**: API authentication (v4.3)
- **DomPDF**: PDF generation for barcode printing (v3.1)
- **Picqer Barcode Generator**: Code-128 barcode generation (v3.2)
- **Laravel Tinker**: Interactive debugging (v2.10)

### Development Tools
- **Pest**: Testing framework (v4.3)
- **Laravel Pint**: Code style formatter (v1.24)
- **Laravel Sail**: Docker development environment (v1.41)
- **Laravel Boost**: Enhanced development tools (v2.1)
- **Laravel Pail**: Log viewer (v1.2)

### Database & Caching
- **MySQL**: Primary database
- **Database Queue**: Job queue driver
- **Database Cache**: Cache driver

## Requirements

- PHP >= 8.2
- Composer
- MySQL 5.7+ or 8.0+
- Node.js & NPM
- XAMPP (or similar LAMP/WAMP stack)

## Installation

### 1. Clone the Repository

```bash
git clone <repository-url>
cd inve-backend
```

### 2. Install Dependencies

```bash
composer install
npm install
```

### 3. Environment Configuration

```bash
cp .env.example .env
php artisan key:generate
```

Update your `.env` file with your database credentials:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=inve_backend
DB_USERNAME=root
DB_PASSWORD=
```

### 4. Database Setup

```bash
php artisan migrate
```

Optional: Seed the database with sample data

```bash
php artisan db:seed
```

### 5. Build Frontend Assets

```bash
npm run build
```

### 6. Queue Worker

Start the queue worker (required for background jobs):

```bash
php artisan queue:work
```

## Configuration

### Mail Configuration

For password reset OTP functionality, configure your mail settings in `.env`:

```env
MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=your-email@gmail.com
MAIL_PASSWORD=your-app-password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=your-email@gmail.com
MAIL_FROM_NAME="${APP_NAME}"
```

### Queue Configuration

The system uses database queues by default:

```env
QUEUE_CONNECTION=database
```

### Storage Configuration

Barcodes are stored using Laravel's storage system:

```env
FILESYSTEM_DISK=local
```

## API Documentation

### Authentication Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/api/register` | Register new user |
| POST | `/api/login` | User login |
| POST | `/api/logout` | User logout (authenticated) |
| GET | `/api/user` | Get authenticated user |
| GET | `/api/me` | Get current user details |

### Password Reset (OTP)

| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/api/password/otp/request` | Request OTP |
| POST | `/api/password/otp/verify` | Verify OTP |
| POST | `/api/password/otp/reset` | Reset password |

### Products & Batches

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET/POST | `/api/products` | List/Create products |
| GET/PUT/DELETE | `/api/products/{id}` | Show/Update/Delete product |
| GET/POST | `/api/batches` | List/Create batches |
| GET/PUT/DELETE | `/api/batches/{batch_code}` | Show/Update/Delete batch |
| GET | `/api/search/{code}` | Search by batch code or barcode |
| GET | `/api/batches/{id}/print-barcode` | Print barcode for batch |

### Warehouses & Branches

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET/POST | `/api/warehouses` | List/Create warehouses |
| GET/PUT/DELETE | `/api/warehouses/{id}` | Show/Update/Delete warehouse |
| GET/POST | `/api/branches` | List/Create branches |
| GET/PUT/DELETE | `/api/branches/{id}` | Show/Update/Delete branch |

### Stock Movements

| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/api/stock/in/{batch_code}` | Stock in operation |
| POST | `/api/stock/out/{batch_code}` | Stock out operation |
| POST | `/api/stock/transfer/{batch_code}` | Transfer between warehouses |

### Movement Approvals

| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/api/movement-approvals/{id}/approve` | Approve movement |
| POST | `/api/movement-approvals/{id}/reject` | Reject movement |

### Stock Levels

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/stock-levels` | Get all stock levels |
| GET | `/api/stock-levels/warehouse/{warehouseId}/batch/{batchId}` | Get specific stock level |
| GET | `/api/stock-levels/scan/{barcode}` | Scan barcode for stock info |

### Barcode Management

| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/api/barcodes` | Generate barcode |
| GET | `/api/barcodes/{code}` | Lookup barcode |
| POST | `/api/batches/{batch}/print` | Print barcode (PDF) |
| GET | `/api/scan/{code}` | Scan barcode |

### Dashboard Analytics

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/dashboard/product-stock/{product_id}` | Stock for specific product |
| GET | `/api/dashboard/product-stock` | Stock for all products |
| GET | `/api/dashboard/recent-transfers` | Recent transfer history |
| GET | `/api/dashboard/warehouse-products/{warehouse_id}` | Products in warehouse |

**Note:** All endpoints except authentication require `Authorization: Bearer {token}` header.

## Database Structure

### Core Tables

- **users**: System users
- **branches**: Company branches
- **warehouses**: Storage locations
- **products**: Product catalog
- **batches**: Product batches with expiry tracking
- **batch_stocks**: Stock levels per batch per warehouse
- **stock_movements**: Stock movement records
- **stock_transactions**: Individual stock transactions
- **stock_snapshots**: Historical stock snapshots
- **movement_approvals**: Approval workflow records
- **barcodes**: Generated barcodes
- **barcode_print_logs**: Barcode printing history
- **audit_logs**: System audit trail
- **password_otps**: Password reset OTPs

## Development

### Run Development Server

```bash
composer run dev
```

This command concurrently runs:
- Laravel development server (port 8000)
- Queue worker
- Vite dev server

### Code Formatting

Format code with Laravel Pint:

```bash
vendor/bin/pint
```

### View Logs

Use Laravel Pail for real-time log viewing:

```bash
php artisan pail
```

### Tinker (Interactive Shell)

```bash
php artisan tinker
```

## Testing

Run the test suite with Pest:

```bash
composer test
# or
php artisan test
```

Run specific tests:

```bash
php artisan test --filter=TestName
```

## Project Scripts

Available composer scripts:

```bash
composer setup        # Initial project setup
composer dev          # Run development servers
composer test         # Run test suite
```

## Key Services

### StockMovementService
Handles all stock movement operations (in/out/transfer) with automatic stock level updates.

### BarcodeService
Generates Code-128 barcodes for batches and manages barcode storage.

### BarcodePrintService
Creates PDF documents with barcodes for printing.

### StockLevelService
Manages real-time stock level calculations across warehouses.

### MovementApprovalService
Handles the approval workflow for stock movements.

## License

This project is open-sourced software licensed under the MIT license.

## Support

For issues and questions, please create an issue in the repository.
