# Business Rules

## 1. General

These rules define the current prototype business logic.

If historical reference documents contain different values, DO NOT automatically overwrite these rules.

If a requirement is unclear, mark it as:
TODO / NEEDS CONFIRMATION

Do not invent business rules.

---

# 2. Project Types

The system supports two project types:

## Commercial

Commercial projects may directly represent:
- Mall
- University
- School
- Commercial building
- Ruko
- Institution
- Other commercial properties

Commercial projects do not have to use:

Project → Cluster → Block → Lot

## Residential

Residential projects may use:

Project
→ Cluster
→ Block
→ Lot / Kavling

---

# 3. Customer

Customer can represent:

- Individual
- CV
- PT
- Institution / Company

Customer data should support:
- Name
- NIK/KTP
- NPWP
- WhatsApp
- Email
- Billing address
- PIC

PIC should be separate related data where appropriate.

---

# 4. Ownership

Ownership connects a customer with a property/project.

### Commercial

Customer
+ Commercial Project
+ Billing Address
+ Area
+ IPL Rate

### Residential

Customer
+ Residential Project
+ Cluster
+ Block
+ Lot/Kavling
+ Billing Address

---

# 5. IPL

IPL is generated based on a billing period.

Example:

01 July 2026 - 01 August 2026

Basic calculation:

IPL = Area × IPL Rate

Example:

Area = 180 m²
Rate = Rp4,500/m²

IPL = 180 × 4,500
IPL = Rp810,000

IPL rates are configurable.

Different customers/properties may have different IPL rates.

Example special pricing:

(Rp4,500 × 50%) × 340 m²
= Rp765,000

Do not assume one global IPL rate.

---

# 6. Water Meter

Water billing uses meter readings.

A meter record should contain:

- Customer / Ownership
- Meter number if applicable
- Previous/current reading
- Reading date
- Meter photo
- Notes

Usage:

Water Usage = Current Reading - Previous Reading

Example:

Previous = 296.02
Current = 301.30

Usage = 5.28 m³

Historical meter readings must be preserved.

---

# 7. Water Pricing

Water pricing may differ between customers/tenants.

Pricing can be negotiated.

The system must support progressive/tiered pricing.

Example:

0–20 m³ = Rp7,500/m³
21–40 m³ = Rp8,500/m³
41–60 m³ = Rp9,500/m³

If usage is 24.58 m³:

20 × Rp7,500
+
4.58 × Rp8,500

The system MUST NOT apply Rp8,500 to the entire 24.58 m³.

Each tier is calculated progressively.

---

# 8. Water Abonemen

Some water charges include a fixed monthly abonemen.

Example:

Abonemen = Rp45,000

Total water:

Abonemen + Progressive Usage Cost

The abonemen value must be configurable.

---

# 9. Tax

For the prototype, use:

DPP Nilai Lain:

(11 / 12) × Subtotal DPP

PPN:

12% × DPP Nilai Lain

Total:

DPP Nilai Lain + PPN

Example:

Subtotal DPP = Rp855,000

DPP Nilai Lain:
11/12 × 855,000
= Rp783,750

PPN:
12% × 783,750
= Rp94,050

Total:
783,750 + 94,050
= Rp877,800

IMPORTANT:

The application must not hardcode tax calculation inside React components.

Use a reusable backend tax service/calculation layer.

Tax rates should be configurable for future changes.

Historical documents may contain older tax values.

For this prototype, the current requirement is PPN 12%.

---

# 10. Invoice

Invoice should contain:

- Invoice number
- Invoice date
- Due date
- Customer
- Billing address
- Project/property
- Billing period
- Description
- Quantity
- Unit
- Unit price
- Subtotal
- DPP Nilai Lain
- PPN
- Total
- Terbilang
- Payment method
- Company information
- Signature

Invoice numbers should be system-generated.

Do not rely on users manually typing invoice numbers.

---

# 11. Combined Invoice

The application must support:

1. Separate invoice
2. Combined invoice

Residential examples may combine:

IPL + Air

Commercial invoices may be separated.

The system should NOT hardcode:

"Residential always combined"

or:

"Commercial always separated"

Invoice grouping should be configurable/determined by billing context.

---

# 12. Electricity

Electricity transactions:

- Pasang Baru
- Tambah Daya
- Perbaikan

Pasang Baru / Tambah Daya commonly consists of:

1. PLN / external charge
2. Management fee

Management fee is configurable.

Example:

PLN charge = Rp2,150,500

Management fee = 15%

Fee:
15% × Rp2,150,500
= Rp322,575

Do not permanently hardcode 15%.

The management fee should be stored as configurable data.

---

# 13. Other Charges

Other charges support:

- Customer
- Charge name
- Amount
- Tax
- Description

Examples:
- Maintenance
- Infrastructure repair
- Pipe repair
- Other agreed charges

Repair/maintenance pricing may be manually entered because there is not necessarily a fixed standard price.

---

# 14. Payment

There is no payment gateway.

Payment occurs outside the system.

Admin records:

- Invoice
- Payment date
- Amount
- Payment method
- Notes
- Payment proof if required

Possible invoice statuses:

- Draft
- Issued
- Unpaid
- Partially Paid
- Paid
- Cancelled

For the prototype, payment processing itself is NOT automated.

---

# 15. Receipt / Kwitansi

When payment is recorded, the system can generate a receipt.

Receipt should contain:

- Receipt number
- Customer
- Amount
- Terbilang
- Payment description
- Payment date
- Company information
- Signature

---

# 16. WhatsApp

For the prototype:

WhatsApp provider = MOCK

The application should simulate:

Invoice
→ PDF
→ WhatsApp message
→ Success/Failure

Save sending history.

Future provider:

Fonnte

The backend should use an abstraction/provider pattern so Fonnte can be integrated later without changing the invoice UI flow.

---

# 17. Dashboard

Dashboard must calculate:

1. Total money received in current month
2. Total money received in current year
3. Outstanding 1–3 months
4. Outstanding 4–6 months
5. Outstanding 7–12 months
6. Outstanding >12 months

These values must come from actual database records.

Do not hardcode dashboard numbers.

---

# 18. Reports

Required reports:

### Periodic Report
- IPL
- Air
- Other charges

### Payment Receipt Report

Reports should support date filtering.

Excel export should be supported where practical.

---

# 19. Soft Delete

Use soft delete for appropriate master/business records.

Do not physically delete important invoice/payment history.

Historical data must remain traceable.

---

# 20. Historical Reference Note

The reference documents represent actual historical workflows.

Examples show:
- Different IPL rates
- Different water rates
- Combined IPL + Air billing
- Separate commercial billing
- Electricity billing with management fee
- Historical tax calculations

Therefore the system must be configurable instead of assuming every historical case uses the same formula.

Historical examples are references, not automatic system rules.