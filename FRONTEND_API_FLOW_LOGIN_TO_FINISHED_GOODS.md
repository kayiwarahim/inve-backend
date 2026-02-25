# Frontend API Guide: Login to Finished Goods Sale

This document gives frontend-ready API calls for the operational flow from login to selling finished goods.

## 1) Base Rules

- Base path: `/api`
- Auth type: Bearer token (Sanctum)
- Add header after login:

```http
Authorization: Bearer {token}
Accept: application/json
Content-Type: application/json
```

- Most endpoints are permission-protected. If missing permission, expect `403`.

## 2) Authentication

### POST `/api/login`

Request body:

```json
{
  "email": "admin@example.com",
  "password": "secret123"
}
```

Success response `200`:

```json
{
  "user": {
    "id": 1,
    "name": "Admin",
    "email": "admin@example.com",
    "email_verified_at": null,
    "phone": null,
    "created_at": "2026-02-25T08:00:00.000000Z",
    "updated_at": "2026-02-25T08:00:00.000000Z",
    "last_login_at": "2026-02-25T09:10:00.000000Z",
    "branch_id": 1,
    "roles": [],
    "permissions": [],
    "effective_permissions": [
      "suppliers.create",
      "source-lots.create"
    ]
  },
  "token": "1|plain-text-token"
}
```

Error `401`:

```json
{
  "message": "Invalid credentials"
}
```

### GET `/api/me`

Use this to restore session/user access on app reload.

Success response `200`: same shape as `login.user` object.

## 3) Master Data Calls (Before Operations)

These are typically loaded in dropdowns.

### GET `/api/branches`

Response `200`:

```json
[
  {
    "id": 1,
    "name": "Mukono Kiyunga",
    "code": "MUK",
    "location": "Mukono",
    "is_main": false,
    "created_at": "2026-02-25T08:00:00.000000Z",
    "updated_at": "2026-02-25T08:00:00.000000Z"
  }
]
```

### POST `/api/branches` (if frontend supports setup)

Request body:

```json
{
  "name": "Kampala Depot",
  "code": "KLA-D",
  "location": "Kampala"
}
```

Success response `200`:

```json
{
  "message": "Branch created successfully",
  "data": {
    "id": 2,
    "name": "Kampala Depot",
    "code": "KLA-D",
    "location": "Kampala",
    "updated_at": "2026-02-25T09:00:00.000000Z",
    "created_at": "2026-02-25T09:00:00.000000Z"
  }
}
```

### GET `/api/warehouses`

Response `200`:

```json
[
  {
    "id": 1,
    "branch_id": 1,
    "name": "Production Room",
    "code": "PROD",
    "type": "processing",
    "created_at": "2026-02-25T08:00:00.000000Z",
    "updated_at": "2026-02-25T08:00:00.000000Z"
  }
]
```

### POST `/api/warehouses` (if frontend supports setup)

Request body:

```json
{
  "branch_id": 1,
  "name": "Cold Room",
  "code": "COLD",
  "type": "cold-storage"
}
```

Success response `200`:

```json
{
  "message": "Warehouse created successfully",
  "data": {
    "id": 3,
    "branch_id": 1,
    "name": "Cold Room",
    "code": "COLD",
    "type": "cold-storage",
    "created_at": "2026-02-25T09:02:00.000000Z",
    "updated_at": "2026-02-25T09:02:00.000000Z"
  }
}
```

### GET `/api/products`

Response `200`:

```json
[
  {
    "id": 1,
    "sku": "LIVE_CHICKEN_KG",
    "name": "Live Chicken",
    "unit": "kg",
    "description": null,
    "created_at": "2026-02-25T08:00:00.000000Z",
    "updated_at": "2026-02-25T08:00:00.000000Z"
  }
]
```

### POST `/api/products` (if frontend supports setup)

Request body:

```json
{
  "sku": "MIXED_PORTIONS",
  "name": "Mixed Portions",
  "unit": "kg",
  "description": "Mixed cut portions"
}
```

Success response `200`:

```json
{
  "message": "Product created successfully",
  "data": {
    "id": 7,
    "sku": "MIXED_PORTIONS",
    "name": "Mixed Portions",
    "unit": "kg",
    "description": "Mixed cut portions",
    "created_at": "2026-02-25T09:03:00.000000Z",
    "updated_at": "2026-02-25T09:03:00.000000Z"
  }
}
```

### GET `/api/suppliers`

Response `200`:

```json
[
  {
    "id": 1,
    "name": "Supplier A",
    "code": "SUP-A",
    "phone": "0700000000",
    "email": "supplier@example.com",
    "address": "Mukono",
    "is_active": true,
    "created_at": "2026-02-25T08:00:00.000000Z",
    "updated_at": "2026-02-25T08:00:00.000000Z"
  }
]
```

### POST `/api/suppliers`

Request body:

```json
{
  "name": "Supplier A",
  "code": "SUP-A",
  "phone": "0700000000",
  "email": "supplier@example.com",
  "address": "Mukono",
  "is_active": true
}
```

Success response `201`:

```json
{
  "message": "Supplier created successfully",
  "data": {
    "id": 1,
    "name": "Supplier A",
    "code": "SUP-A",
    "phone": "0700000000",
    "email": "supplier@example.com",
    "address": "Mukono",
    "is_active": true,
    "created_at": "2026-02-25T09:05:00.000000Z",
    "updated_at": "2026-02-25T09:05:00.000000Z"
  }
}
```

## 4) Receive Live Chicken Lot

### POST `/api/source-lots`

Creates source lot + root batch + stock-in + barcode.

Request body:

```json
{
  "supplier_id": 1,
  "branch_id": 1,
  "source_lot_code": "SRC-2026-0001",
  "received_weight_kg": 1000,
  "received_at": "2026-02-25 09:20:00",
  "warehouse_id": 1,
  "product_id": 1,
  "vehicle_number": "UBA-123A",
  "delivery_note_number": "DN-4432",
  "remarks": "Morning intake",
  "expires_at": "2026-03-05"
}
```

Success response `201`:

```json
{
  "message": "Source lot received successfully",
  "data": {
    "source_lot": {
      "id": 1,
      "supplier_id": 1,
      "branch_id": 1,
      "source_lot_code": "SRC-2026-0001",
      "received_weight_kg": "1000.000",
      "received_at": "2026-02-25T09:20:00.000000Z",
      "received_by": 1,
      "vehicle_number": "UBA-123A",
      "delivery_note_number": "DN-4432",
      "remarks": "Morning intake",
      "created_at": "2026-02-25T09:20:10.000000Z",
      "updated_at": "2026-02-25T09:20:10.000000Z",
      "supplier": {},
      "branch": {}
    },
    "batch": {
      "id": 10,
      "product_id": 1,
      "source_lot_id": 1,
      "batch_code": "B-SRC-20260225-092010-123456",
      "manufactured_at": "2026-02-25",
      "expires_at": "2026-03-05",
      "created_at": "2026-02-25T09:20:10.000000Z",
      "updated_at": "2026-02-25T09:20:10.000000Z",
      "product": {},
      "barcode": {}
    }
  }
}
```

Validation error `422` example:

```json
{
  "message": "Warehouse does not belong to the provided branch."
}
```

### GET `/api/source-lots`

Response `200`: list with embedded `supplier` and `branch`.

### GET `/api/source-lots/{sourceLotId}`

Response `200`:

```json
{
  "source_lot": {
    "id": 1,
    "source_lot_code": "SRC-2026-0001",
    "supplier": {},
    "branch": {},
    "transformation_lines": []
  },
  "root_batch": {
    "id": 10,
    "batch_code": "B-SRC-20260225-092010-123456",
    "barcode": {},
    "batch_stocks": []
  }
}
```

## 5) Process Conversions (Split / Merge / Byproducts / Waste)

### POST `/api/lot-transformations`

Use this for slaughter, EV room, cutting, sorting, bagging, etc.

Request body:

```json
{
  "process_step_id": 1,
  "warehouse_id": 1,
  "branch_id": 1,
  "transformed_at": "2026-02-25 10:00:00",
  "remarks": "EV room removal",
  "inputs": [
    {
      "batch_id": 10,
      "product_id": 1,
      "weight_kg": 1000,
      "remarks": "Root carcass input"
    }
  ],
  "outputs": [
    {
      "product_id": 2,
      "weight_kg": 900,
      "remarks": "Post-EV carcass"
    }
  ],
  "byproducts": [
    {
      "product_id": 8,
      "weight_kg": 30
    },
    {
      "product_id": 9,
      "weight_kg": 20
    },
    {
      "product_id": 10,
      "weight_kg": 25
    },
    {
      "product_id": 11,
      "weight_kg": 25
    }
  ],
  "waste": []
}
```

Success response `201`:

```json
{
  "message": "Transformation posted successfully",
  "data": {
    "id": 1,
    "transformation_code": "TR-EV_ROOM-20260225-100000-123456",
    "process_step_id": 1,
    "warehouse_id": 1,
    "branch_id": 1,
    "performed_by": 1,
    "transformed_at": "2026-02-25T10:00:00.000000Z",
    "remarks": "EV room removal",
    "created_at": "2026-02-25T10:00:03.000000Z",
    "updated_at": "2026-02-25T10:00:03.000000Z",
    "process_step": {},
    "warehouse": {},
    "branch": {},
    "lines": [
      {
        "line_type": "input",
        "batch_id": 10,
        "source_lot_id": 1,
        "product_id": 1,
        "weight_kg": "1000.000",
        "remarks": "Root carcass input"
      },
      {
        "line_type": "output",
        "batch_id": 11,
        "source_lot_id": null,
        "product_id": 2,
        "weight_kg": "900.000",
        "remarks": "Post-EV carcass"
      }
    ]
  }
}
```

Validation error `422` examples:

```json
{
  "message": "Mass balance validation failed.",
  "details": {
    "input_kg": 1000,
    "output_kg": 890,
    "byproducts_kg": 40,
    "waste_kg": 20,
    "difference_kg": 50
  }
}
```

```json
{
  "message": "Warehouse does not belong to the provided branch."
}
```

### POST `/api/lot-transformations/repack-mixed`

Use this to replenish mixed portions from multiple cut batches.

Request body:

```json
{
  "warehouse_id": 1,
  "branch_id": 1,
  "mixed_product_id": 7,
  "mixed_batch_id": null,
  "transformed_at": "2026-02-25 14:00:00",
  "inputs": [
    {
      "batch_id": 21,
      "weight_kg": 120
    },
    {
      "batch_id": 22,
      "weight_kg": 80
    },
    {
      "batch_id": 23,
      "weight_kg": 50
    },
    {
      "batch_id": 24,
      "weight_kg": 30
    }
  ],
  "waste_kg": 10,
  "waste_product_id": 12,
  "remarks": "Mixed portions replenishment run"
}
```

Success response `201`:

```json
{
  "message": "Mixed portions replenished successfully",
  "data": {
    "id": 2,
    "transformation_code": "TR-MIX_BUILD-20260225-140000-123456",
    "lines": []
  }
}
```

### GET `/api/lot-transformations`

Response `200`: transformation list with `processStep`, `warehouse`, `branch`, and `lines` (`batch.product`, `sourceLot`).

## 6) Move Stock Between Rooms / Branches

### POST `/api/stock/transfer/{batch_code}`

Use for internal movement or branch dispatch while keeping the same batch.

Example:

`POST /api/stock/transfer/B-OUTPUT-20260225-103000-123456`

Request body:

```json
{
  "from_warehouse_id": 3,
  "to_warehouse_id": 7,
  "quantity": 200,
  "reason": "Move to cold room"
}
```

Success response `200`:

```json
{
  "id": 14,
  "product_id": 7,
  "source_lot_id": null,
  "batch_code": "B-OUTPUT-20260225-103000-123456",
  "manufactured_at": "2026-02-25",
  "expires_at": null,
  "created_at": "2026-02-25T10:30:00.000000Z",
  "updated_at": "2026-02-25T10:30:00.000000Z"
}
```

### POST `/api/stock/in/{batch_code}`

Request body:

```json
{
  "warehouse_id": 7,
  "quantity": 50,
  "reason": "Manual stock in"
}
```

Success response `200`: returns fresh batch object (same shape as above).

### POST `/api/stock/out/{batch_code}` (Final sale dispatch)

This is the endpoint to deduct sold finished goods from stock.

Request body:

```json
{
  "warehouse_id": 7,
  "quantity": 15,
  "reason": "Retail sale INV-2026-0012"
}
```

Success response `200`: returns fresh batch object.

Error `422`:

```json
{
  "message": "Insufficient quantity"
}
```

## 7) GET Calls for Frontend Visibility During Operations

### GET `/api/stock-levels`

Optional query params:

- `warehouse_id`
- `batch_id`

Response `200`:

```json
{
  "data": [
    {
      "id": 100,
      "batch_id": 14,
      "warehouse_id": 7,
      "quantity": "185.000",
      "warehouse": {},
      "batch": {}
    }
  ]
}
```

### GET `/api/stock-levels/warehouse/{warehouseId}/batch/{batchId}`

Response `200`:

```json
{
  "warehouse_id": 7,
  "batch_id": 14,
  "quantity": "185.000"
}
```

### GET `/api/scan/{barcodeCode}`

Response `200`:

```json
{
  "batch": {
    "id": 14,
    "batch_code": "B-OUTPUT-20260225-103000-123456",
    "manufactured_at": "2026-02-25",
    "expires_at": null
  },
  "product": {
    "id": 7,
    "name": "Mixed Portions",
    "sku": "MIXED_PORTIONS"
  },
  "locations": [
    {
      "id": 7,
      "name": "Cold Room",
      "quantity": "185.000"
    }
  ]
}
```

### GET `/api/trace/{barcode_or_batch_code}`

Use for recall, quality incident investigation, and source verification.

Response `200` (top-level shape):

```json
{
  "batch": {
    "id": 14,
    "batch_code": "B-OUTPUT-20260225-103000-123456",
    "product": "Mixed Portions",
    "sku": "MIXED_PORTIONS",
    "barcode": "BR-XYZ123"
  },
  "current_locations": [],
  "movement_history": [],
  "lineage": {},
  "source_lots": []
}
```

If not found: `404`

```json
{
  "message": "No batch found for the provided code."
}
```

## 8) Suggested Frontend Call Order (Production Flow)

1. Login:
- `POST /api/login`
- `GET /api/me`

2. Load dropdown data:
- `GET /api/branches`
- `GET /api/warehouses`
- `GET /api/products`
- `GET /api/suppliers`

3. Receive live birds:
- `POST /api/source-lots`

4. Process conversions:
- `POST /api/lot-transformations` (repeat by stage)
- `POST /api/lot-transformations/repack-mixed` (when needed)

5. Internal/branch movement:
- `POST /api/stock/transfer/{batch_code}`

6. Selling finished goods:
- `POST /api/stock/out/{batch_code}`

7. Visibility/trace:
- `GET /api/stock-levels`
- `GET /api/scan/{code}`
- `GET /api/trace/{code}`

## 9) Common Error Handling Notes

- `401`: token missing/invalid, or bad login credentials.
- `403`: authenticated but lacks permission.
- `404`: not found (batch, barcode, source lot, etc.).
- `422`: validation/business rule failure (mass balance, warehouse mismatch, insufficient quantity).

