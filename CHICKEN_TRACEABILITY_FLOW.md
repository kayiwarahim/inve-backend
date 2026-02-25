# Chicken Processing Traceability Flow (Detailed)

## 1. Purpose
This document defines the full operational and data flow for your chicken processing business in `inve-backend`, from live chicken intake at Mukono Kiyunga to final distribution through Kampala depot and onward to satellite branches.

The core objective is:
- Track every kilogram of product through each process step.
- Track merges/splits (especially mixed portions built from different parts/lots).
- Trace any final product barcode backward to source live chicken lot and supplier.
- Support recalls, quality investigations, and yield monitoring.

## 2. Physical Operations Mapped to System Steps
Your physical flow:
1. Receive live chicken at Mukono Kiyunga.
2. Slaughter.
3. Move to EV room (remove gizzard, heads, feet, liver).
4. Move whole carcass to spin chiller (wash/clean).
5. Move to production room.
6. Sort whole birds by weights (1.1kg to 1.7kg).
7. Cut portions (thighs, legs, breast fillet, drumsticks).
8. Build mixed portions (merge of multiple part lots).
9. Move to chiller locations.
10. Move to bagging room.
11. Move to cold room.
12. Dispatch to Kampala central depot.
13. Distribute to Kisaasi, Ntinda, Kawempe, Bugolobi.

System mapping:
- `source_lots`: live chicken intake.
- `batches`: lot identity for stock-carrying products.
- `batch_stocks`: current kg per batch per warehouse.
- `stock_transactions`: stock movement/audit timeline.
- `lot_transformations` + `lot_transformation_lines`: process and conversion graph (split/merge lineage).
- `barcodes`: unique scannable identity for each batch.

## 3. Master Data Setup
Before operations begin:
1. Branches:
- Mukono Kiyunga (processing branch).
- Kampala Depot (central distribution).
- Kisaasi, Ntinda, Kawempe, Bugolobi (distribution branches).

2. Warehouses (examples):
- Slaughter Area
- EV Room
- Spin Chiller
- Production Room
- Chiller
- Bagging Room
- Cold Room
- Dispatch Bay

3. Suppliers:
- Create/maintain via `/api/suppliers`.

4. Products (all unit = `kg`):
- `LIVE_CHICKEN_KG`
- Whole sorted SKUs: `WHOLE_1_1`, `WHOLE_1_2`, ... `WHOLE_1_7`
- Cuts: `THIGHS`, `LEGS`, `BREAST_FILLET`, `DRUMSTICKS`
- Mixed SKU: `MIXED_PORTIONS`
- Optional byproducts: `GIZZARD`, `HEAD`, `FEET`, `LIVER`

5. Process Steps (`process_steps`):
- `SLAUGHTER`, `EV_ROOM`, `SPIN_CHILLER`, `PRODUCTION_SORTING`, `CUTTING`, `MIX_BUILD`, `BAGGING`, `COLD_ROOM`, `DISPATCH`

## 4. End-to-End Core Flow

### 4.1 Source Lot Intake (Live Chicken Reception)
Endpoint:
- `POST /api/source-lots`

What happens:
1. Source lot is created with supplier, branch, received kg, time, vehicle/note metadata.
2. A root batch is created and linked to that source lot.
3. Stock is placed into intake warehouse in `batch_stocks`.
4. `stock_transactions` logs an `in` event.
5. Barcode is generated for the root batch.

Outcome:
- You can scan and identify origin immediately.

### 4.2 Step-by-Step Processing Transformations
Endpoint:
- `POST /api/lot-transformations`

Each process event captures:
1. Process step (e.g., EV room).
2. Warehouse and branch where it happened.
3. Input lines (existing batches/source lots consumed).
4. Output lines (new/updated batches produced).
5. Byproduct lines (optional).
6. Waste lines (optional).

System guarantees:
1. Input stock availability is checked.
2. Inputs are deducted from stock.
3. Outputs/byproducts are added to stock.
4. Output batches get barcodes if missing.
5. Full transformation lineage is recorded.
6. Mass balance validation is enforced:
- `input_kg ~= output_kg + byproduct_kg + waste_kg` (small tolerance).

### 4.3 Internal and Branch Transfers
Endpoints:
- `POST /api/stock/transfer/{batch_code}`
- `POST /api/stock/in/{batch_code}`
- `POST /api/stock/out/{batch_code}`

Used for:
1. Movement from process room to room.
2. Dispatch from Mukono to Kampala depot.
3. Redistribution to other branches.

Traceability rule:
- Transfers change location only.
- Batch identity stays constant, preserving lineage.

### 4.4 Trace Query (Backward Ancestry)
Endpoint:
- `GET /api/trace/{barcode_or_batch_code}`

Returns:
1. Current stock locations.
2. Movement history.
3. Recursive lineage tree of transformations.
4. Deduplicated source lots and suppliers upstream.
5. Contribution percentages per input in blend/merge operations.

## 5. Mixed Portions Merge Logic (Critical Scenario)
Use case:
- Mixed portions are not from whole chicken direct conversion.
- They are built by merging cuts from different batches/lots.

Example:
1. Input lines:
- Thigh batch A: 50kg
- Leg batch B: 30kg
- Drumstick batch C: 20kg
2. Output line:
- Mixed portions batch M: 95kg
3. Waste line:
- Trim waste: 5kg

Why this works:
1. `M` stores parent links to all inputs (`A`, `B`, `C`) through transformation lines.
2. Trace endpoint recursively walks those parent nodes.
3. System can show mix composition and source contribution percentages.

## 5.1 Finished Product Flexibility (Whole, Cut, or Repack)
The system supports all three finished-goods paths:
1. Sell as whole chicken:
- Use transformation outputs for `WHOLE_1_2` ... `WHOLE_1_7`.
- These are finished product batches and can move directly to chiller/bagging/cold room/distribution.

2. Cut into portions:
- From whole/carcass inputs, create outputs such as `THIGHS`, `LEGS`, `BREAST_FILLET`, `DRUMSTICKS`.
- These are also finished products and can be sold directly.

3. Repack cuts into mixed portions:
- When mixed stock is low or by planning choice, consume available cut batches and produce/add to mixed batch.
- Dedicated endpoint:
`POST /api/lot-transformations/repack-mixed`
- This records a `MIX_BUILD` transformation, deducts cut stocks, increases mixed stock, and keeps full lineage.

## 6. Detailed Scenario Coverage

### Scenario A: One input -> many outputs (split)
Example:
- Whole chicken input -> 1.1kg, 1.2kg sorted batches + parts.
System:
- One transformation with multiple `output` lines.

### Scenario B: Many inputs -> one output (merge/blend)
Example:
- Multiple cut lots -> one mixed portions batch.
System:
- One transformation with multiple `input` lines and one `output` line.

### Scenario C: Many inputs -> many outputs
Example:
- Mixed processing run producing both mixed portions and byproducts.
System:
- One transformation with multiple inputs and multiple outputs/byproducts.

### Scenario D: Source-lot-only input
If batch is not explicitly provided:
- Input can resolve from `source_lot_id` where applicable.

### Scenario E: Rework loop
If product is re-opened/reworked:
- Create new transformation referencing existing batch as input.
- Output a new batch (or replenish same batch if policy allows).

### Scenario F: Inter-branch transfer chain
Mukono -> Kampala -> Ntinda:
- Each movement logged in `stock_transactions`.
- No loss of ancestry because batch ID remains the same.

### Scenario G: Product depletion
When consumed to zero:
- Stock row is removed for that warehouse/batch.
- History remains in transactions and transformations.

## 7. Exceptions and Validation Outcomes

### 7.1 Insufficient Stock
Condition:
- Input requested > available in warehouse.
Result:
- Transformation rejected (`422`).
- No partial commit due to DB transaction.

### 7.2 Mass Balance Failure
Condition:
- Input total does not match output+byproduct+waste within tolerance.
Result:
- Transformation rejected (`422`) with mismatch details.

### 7.3 Warehouse/Branch Mismatch
Condition:
- Warehouse does not belong to posted branch.
Result:
- Request rejected (`422`).

### 7.4 Unknown Trace Code
Condition:
- No barcode and no batch code match.
Result:
- Trace endpoint returns `404`.

## 8. Recall and Incident Response Flow
Typical recall starting points:
1. Supplier complaint on source lot.
2. Lab failure on final mixed batch.
3. Quality issue in one branch.

Backward trace:
1. Scan affected final product barcode.
2. Call `/api/trace/{code}`.
3. Identify all source lots and suppliers upstream.

Forward impact assessment:
1. Start with recalled source lot.
2. Find all transformations where it appears in input ancestry.
3. Enumerate all affected child batches and branch locations.
4. Freeze stock and dispatch retrieval notices.

Operational action:
1. Mark affected batches unavailable in business process.
2. Stop transfers/sales for impacted batch codes.
3. Export affected stock by branch and warehouse for recall execution.

## 9. Yield and Performance Monitoring
For each transformation run:
1. Input kg
2. Output kg
3. Byproduct kg
4. Waste kg
5. Yield %

Useful KPIs:
1. Yield by process step (EV room, cutting, mix build).
2. Waste by day/shift/operator/branch.
3. Supplier-to-yield correlation.
4. Batch aging and dwell time by warehouse.

## 10. Barcode Semantics and Usage
Current behavior:
1. New output batches receive barcodes if missing.
2. Barcode resolves batch identity; batch resolves lineage.

Recommended operational policy:
1. Print and attach at batch creation time.
2. Scan on every handoff (warehouse move, bagging, dispatch, receiving).
3. Never merge physically without posting digital transformation event first.

## 11. Process Control Rules (Recommended SOP)
1. No stock movement without barcode scan.
2. No transformation without explicit inputs/outputs.
3. No manual stock adjustment without reason and supervisor approval.
4. Enforce FEFO/FIFO input selection policy for production.
5. Lock dispatch for batches under quality hold.

## 12. API Flow Summary
Main operational API order:
1. Create suppliers: `POST /api/suppliers`
2. Receive live lot: `POST /api/source-lots`
3. Post each process conversion: `POST /api/lot-transformations`
4. Transfer between locations/branches: stock movement endpoints
5. Trace any product at any point: `GET /api/trace/{code}`

## 13. Data Integrity and Auditability
Auditability is preserved by:
1. Immutable historical rows (`stock_transactions`, transformation lines).
2. Transactional writes for stock mutation and lineage creation.
3. Unique identifiers:
- `source_lot_code`
- `batch_code`
- `barcode.code`
- `transformation_code`

## 14. Operational Examples (Concrete)

### Example 1: EV Room Removal
1. Input:
- Root carcass batch: 1000kg
2. Outputs:
- Whole carcass (post-EV): 900kg
- Gizzard: 30kg
- Liver: 20kg
- Head: 25kg
- Feet: 25kg
3. Waste:
- 0kg

Mass balance:
- 1000 = 900 + 30 + 20 + 25 + 25

### Example 2: Mixed Portions Build
1. Inputs:
- Thigh batch T1: 120kg
- Legs batch L1: 80kg
- Drumsticks batch D1: 50kg
- Breast batch B1: 30kg
2. Output:
- Mixed portions M1: 270kg
3. Waste:
- 10kg

Mass balance:
- 280 = 270 + 10

Contribution:
- T1: 42.8571%
- L1: 28.5714%
- D1: 17.8571%
- B1: 10.7143%

## 15. Current Implementation vs Next Enhancements
Implemented now:
1. Supplier/source lot management.
2. Transformation engine with split/merge and mass-balance validation.
3. Trace endpoint with recursive upstream lineage.
4. Barcode linkage for output batches.
5. Basic feature tests for mixed-lot trace.

Recommended next enhancements:
1. FEFO input suggestion endpoint.
2. Recall impact endpoint (source lot -> all affected downstream batches/branches).
3. Quality hold status on batches.
4. Shift/operator productivity reports.
5. Approval workflow for adjustments and high-risk transformations.
