# IPU Billing & Invoice Management System — Technical Architecture

> **Status:** Architecture finalized — awaiting Phase 1 implementation approval.
> **Source of truth:** PROJECT.md, BUSINESS_RULES.md, PROTOTYPE_SCOPE.md
> **Last updated:** Post-review — prototype decisions applied

---

## Table of Contents

1. [Project Understanding](#1-project-understanding)
2. [System Architecture](#2-system-architecture)
3. [Database Design / ERD](#3-database-design--erd)
4. [Business Logic](#4-business-logic)
5. [REST API Structure](#5-rest-api-structure)
6. [Frontend Architecture](#6-frontend-architecture)
7. [Backend Architecture](#7-backend-architecture)
8. [Module Dependencies](#8-module-dependencies)
9. [Main Data Flows](#9-main-data-flows)
10. [Finalized Decisions & Remaining Questions](#10-finalized-decisions--remaining-questions)
11. [Technical and Business Risks](#11-technical-and-business-risks)
12. [Phased Implementation Roadmap](#12-phased-implementation-roadmap)
13. [Recommended Phase 1](#13-recommended-phase-1)

---

## 1. Project Understanding

### Purpose
A web-based internal administrative tool that replaces an Excel-based workflow for managing
property billing, invoices, payments, and receipts. The system serves a property/estate
management company (IPU Land / Poj City Semarang context) that manages both residential
and commercial properties.

### Main Users

| Role | Responsibility |
|---|---|
| **Developer** | System administration, user management, full access |
| **Admin** | Daily operations: billing, invoices, payments, reports |

### Main Business Workflow

```
Master Data Setup
  → Customer + Property/Ownership registration
  → Meter reading input (water)
  → Billing generation (IPL, Water, Electricity, Other)
  → Tax calculation (DPP Nilai Lain + PPN 12%)
  → Invoice generation (combined or separate)
  → PDF generation
  → Mock WhatsApp delivery
  → External payment by customer
  → Admin records payment
  → Invoice marked Paid
  → Receipt generated
  → Dashboard & Reports updated
```

### Core Problem Solved
Eliminates manual Excel calculations, manual invoice creation, manual PDF handling,
and manual WhatsApp delivery — replacing them with a structured, automated workflow
with stored history.

### Main Modules

1. Authentication & Role Management
2. Master Data (Company, Customer, PIC, Project, Cluster, Block, Lot, Pricing, Signature)
3. Ownership (links Customer to Property)
4. Meter Reading
5. Billing Engine (IPL, Water, Electricity, Other Charges)
6. Tax Calculation Service
7. Invoice (separate & combined)
8. PDF Generation
9. Mock WhatsApp
10. Payment Recording
11. Receipt Generation
12. Reports & Dashboard

---

## 2. System Architecture

### Overall Architecture

```
┌──────────────────────────────────────────────┐
│              React + Vite (Frontend)          │
│  React Router | Axios | Zustand | RQ          │
└──────────────────┬───────────────────────────┘
                   │ HTTPS REST API (JSON)
┌──────────────────▼───────────────────────────┐
│         CodeIgniter 4 (Backend API)           │
│  Controllers → Services → Models             │
│  BillingService | TaxService | PdfService    │
│  WhatsAppService → MockWhatsAppProvider      │
│                 (→ FonnteProvider, future)    │
└──────────────────┬───────────────────────────┘
                   │
┌──────────────────▼───────────────────────────┐
│               MySQL Database                  │
└──────────────────────────────────────────────┘
```

### Component Interaction Summary

| Layer | Technology | Responsibility |
|---|---|---|
| Frontend | React + Vite | UI, forms, display, PDF preview trigger |
| API | CI4 REST Controllers | Route handling, request validation, JSON responses |
| Business Logic | CI4 Services | All calculations: IPL, water tiers, tax, invoice totals |
| Data | CI4 Models | DB queries only — select, insert, update, soft delete |
| PDF | DomPDF (PHP) | Server-side PDF generation |
| WhatsApp | WhatsAppService | Provider-abstracted delivery simulation |
| Storage | MySQL | All persistent data |

**Key principle:** React never performs billing calculations. All monetary logic lives
in the backend Services layer.

### WhatsApp Provider Architecture

```
WhatsAppService (orchestrator)
  ├── MockWhatsAppProvider   ← prototype uses this
  └── FonnteWhatsAppProvider ← future plug-in replacement (stub only)
```

`WhatsAppService` calls `send(phone, message, pdfPath)` on whichever provider is bound.
Switching from Mock to Fonnte requires changing one binding — the invoice workflow is untouched.

---

## 3. Database Design / ERD

### Design Principles

- Soft delete (`deleted_at` DATETIME NULL) on all master and business data
- `created_at` / `updated_at` on every table
- `DECIMAL(15,2)` for all monetary values
- `DECIMAL(10,2)` for meter readings, area, and quantity values
- Invoice and payment records are **never physically deleted**
- All foreign keys reference primary keys of their parent tables

---

### Relationship Overview

```
companies ──< projects ──< clusters ──< blocks ──< lots
companies ──< customers ──< pics
customers ──< ownerships >── projects
ownerships >── ipl_rates
ownerships >── water_rate_groups ──< water_rate_tiers
ownerships ──< meter_readings
ownerships ──< billing_items >── meter_readings
billing_items ──< billing_item_tiers
invoices >── customers
invoices >── companies
invoices ──< invoice_items >── billing_items
invoices ──< payments ──< receipts
invoices ──< whatsapp_logs
invoices >── signatures
users >── roles ──< menu_permissions
tax_configurations (standalone, system-wide active record)
```

---

### Combined vs Separate Invoice Representation

**Separate invoice:** `invoice_type = 'separate'`, `ownership_id` is set.
Each invoice covers one billing type (IPL only, or Water only) for one ownership.
`invoice_items` contains one or a few rows.

**Combined invoice:** `invoice_type = 'combined'`, `ownership_id` may be set
(same customer/ownership). `invoice_items` contains rows from multiple billing types
(e.g. IPL row + Water rows). One combined tax-calculated total applies.

The grouping decision is made at invoice generation time by the admin — it is **not**
hardcoded per project type.

---

### Table: `companies`

**Purpose:** Stores the property management company identity used on invoices and receipts.

| Field | Type | Notes |
|---|---|---|
| `id` | BIGINT PK AI | |
| `name` | VARCHAR(255) | Company name |
| `address` | TEXT | |
| `phone` | VARCHAR(50) | |
| `email` | VARCHAR(150) | |
| `npwp` | VARCHAR(50) | |
| `logo_path` | VARCHAR(255) | |
| `created_at` | DATETIME | |
| `updated_at` | DATETIME | |
| `deleted_at` | DATETIME NULL | Soft delete |

---

### Table: `customers`

**Purpose:** Stores all customer records (individual, CV, PT, institution).

| Field | Type | Notes |
|---|---|---|
| `id` | BIGINT PK AI | |
| `company_id` | BIGINT FK → companies | |
| `name` | VARCHAR(255) | |
| `customer_type` | ENUM('individual','cv','pt','institution') | |
| `nik` | VARCHAR(50) NULL | |
| `npwp` | VARCHAR(50) NULL | |
| `whatsapp` | VARCHAR(50) | |
| `email` | VARCHAR(150) NULL | |
| `billing_address` | TEXT | |
| `notes` | TEXT NULL | |
| `created_at` | DATETIME | |
| `updated_at` | DATETIME | |
| `deleted_at` | DATETIME NULL | Soft delete |

**Indexes:** `name`, `whatsapp`

---

### Table: `pics`

**Purpose:** Person(s) in charge linked to a customer.

| Field | Type | Notes |
|---|---|---|
| `id` | BIGINT PK AI | |
| `customer_id` | BIGINT FK → customers | |
| `name` | VARCHAR(255) | |
| `phone` | VARCHAR(50) | |
| `email` | VARCHAR(150) NULL | |
| `position` | VARCHAR(100) NULL | |
| `is_primary` | TINYINT(1) DEFAULT 0 | |
| `created_at` | DATETIME | |
| `updated_at` | DATETIME | |
| `deleted_at` | DATETIME NULL | Soft delete |

---

### Table: `projects`

**Purpose:** Defines a property project (residential or commercial).

| Field | Type | Notes |
|---|---|---|
| `id` | BIGINT PK AI | |
| `company_id` | BIGINT FK → companies | |
| `name` | VARCHAR(255) | |
| `project_type` | ENUM('residential','commercial') | |
| `address` | TEXT NULL | |
| `notes` | TEXT NULL | |
| `created_at` | DATETIME | |
| `updated_at` | DATETIME | |
| `deleted_at` | DATETIME NULL | Soft delete |

---

### Table: `clusters`

**Purpose:** Subdivision of a residential project. Residential only.

| Field | Type | Notes |
|---|---|---|
| `id` | BIGINT PK AI | |
| `project_id` | BIGINT FK → projects | |
| `name` | VARCHAR(255) | |
| `created_at` | DATETIME | |
| `updated_at` | DATETIME | |
| `deleted_at` | DATETIME NULL | Soft delete |

---

### Table: `blocks`

**Purpose:** Block within a cluster. Required level in the residential hierarchy for V1.

| Field | Type | Notes |
|---|---|---|
| `id` | BIGINT PK AI | |
| `cluster_id` | BIGINT FK → clusters | |
| `name` | VARCHAR(100) | |
| `created_at` | DATETIME | |
| `updated_at` | DATETIME | |
| `deleted_at` | DATETIME NULL | Soft delete |

**V1 Decision:** Residential hierarchy is always Project → Cluster → Block → Lot.
A cluster cannot directly contain lots without a block in V1.
Commercial projects do not use this hierarchy.

---

### Table: `lots`

**Purpose:** Individual lot/kavling within a block.

| Field | Type | Notes |
|---|---|---|
| `id` | BIGINT PK AI | |
| `block_id` | BIGINT FK → blocks | |
| `lot_number` | VARCHAR(50) | Kavling number/label |
| `area` | DECIMAL(10,2) NULL | m² if known at lot level |
| `notes` | TEXT NULL | |
| `created_at` | DATETIME | |
| `updated_at` | DATETIME | |
| `deleted_at` | DATETIME NULL | Soft delete |

---

### Table: `ipl_rates`

**Purpose:** Configurable IPL pricing per project. Supports multiple rates and special pricing.

| Field | Type | Notes |
|---|---|---|
| `id` | BIGINT PK AI | |
| `project_id` | BIGINT FK → projects | |
| `name` | VARCHAR(100) | Label, e.g. "Standard", "50% Khusus" |
| `rate_per_sqm` | DECIMAL(15,2) | Rp per m² |
| `effective_date` | DATE | When this rate is valid from |
| `notes` | TEXT NULL | |
| `created_at` | DATETIME | |
| `updated_at` | DATETIME | |
| `deleted_at` | DATETIME NULL | Soft delete |

**Note:** Special pricing (e.g. 50% rate) is stored as a separate `ipl_rates` record.
Ownership references the applicable rate via `ipl_rate_id`.

---

### Table: `water_rate_groups`

**Purpose:** Named group of water pricing tiers, assigned per ownership.

| Field | Type | Notes |
|---|---|---|
| `id` | BIGINT PK AI | |
| `project_id` | BIGINT FK → projects | |
| `name` | VARCHAR(100) | e.g. "Residential Standard" |
| `abonemen` | DECIMAL(15,2) DEFAULT 0 | Fixed monthly charge |
| `created_at` | DATETIME | |
| `updated_at` | DATETIME | |
| `deleted_at` | DATETIME NULL | Soft delete |

---

### Table: `water_rate_tiers`

**Purpose:** Progressive pricing tiers within a water rate group.

| Field | Type | Notes |
|---|---|---|
| `id` | BIGINT PK AI | |
| `water_rate_group_id` | BIGINT FK → water_rate_groups | |
| `min_usage` | DECIMAL(10,2) | Lower bound m³ |
| `max_usage` | DECIMAL(10,2) NULL | NULL = unlimited (last tier) |
| `rate_per_m3` | DECIMAL(15,2) | Price per m³ in this tier |
| `created_at` | DATETIME | |
| `updated_at` | DATETIME | |

**Note:** Backend validates that tiers have no gaps or overlaps before saving.

---

### Table: `tax_configurations`

**Purpose:** Configurable tax rules — prevents hardcoding PPN/DPP formulas.

| Field | Type | Notes |
|---|---|---|
| `id` | BIGINT PK AI | |
| `label` | VARCHAR(100) | e.g. "PPN 12% (2024+)" |
| `dpp_multiplier_numerator` | INT | 11 |
| `dpp_multiplier_denominator` | INT | 12 |
| `ppn_rate` | DECIMAL(5,4) | 0.1200 |
| `effective_date` | DATE | |
| `is_active` | TINYINT(1) DEFAULT 0 | Only one active at a time |
| `created_at` | DATETIME | |
| `updated_at` | DATETIME | |

**Constraint:** Unique partial index ensures only one `is_active = 1` record exists.

---

### Table: `ownerships`

**Purpose:** Central entity linking a customer to a property with full billing configuration.

| Field | Type | Notes |
|---|---|---|
| `id` | BIGINT PK AI | |
| `customer_id` | BIGINT FK → customers | |
| `project_id` | BIGINT FK → projects | |
| `cluster_id` | BIGINT FK NULL → clusters | Residential only |
| `block_id` | BIGINT FK NULL → blocks | Residential only |
| `lot_id` | BIGINT FK NULL → lots | Residential only |
| `billing_address` | TEXT | |
| `area` | DECIMAL(10,2) | m² used for IPL calculation |
| `ipl_rate_id` | BIGINT FK → ipl_rates | Which IPL rate applies |
| `water_rate_group_id` | BIGINT FK NULL → water_rate_groups | NULL = no water billing |
| `ownership_type` | ENUM('residential','commercial') | |
| `start_date` | DATE | |
| `end_date` | DATE NULL | NULL = currently active |
| `notes` | TEXT NULL | |
| `created_at` | DATETIME | |
| `updated_at` | DATETIME | |
| `deleted_at` | DATETIME NULL | Soft delete |

**Indexes:** `customer_id`, `project_id`

**V1 Hierarchy rules:**
- Residential: `cluster_id`, `block_id`, `lot_id` are all required.
- Commercial: `cluster_id`, `block_id`, `lot_id` are all NULL. Structure is Project → Ownership only.
- One invoice is always associated with one ownership in V1. Cross-ownership invoices are not supported.
- One customer may have multiple ownerships. Each is billed independently.

---

### Table: `meter_readings`

**Purpose:** Records water meter readings per ownership. History is preserved.

| Field | Type | Notes |
|---|---|---|
| `id` | BIGINT PK AI | |
| `ownership_id` | BIGINT FK → ownerships | |
| `meter_number` | VARCHAR(100) NULL | Physical meter identifier |
| `reading_date` | DATE | |
| `previous_reading` | DECIMAL(10,2) | |
| `current_reading` | DECIMAL(10,2) | |
| `usage` | DECIMAL(10,2) | Computed: current - previous |
| `photo_path` | VARCHAR(255) NULL | Upload path |
| `notes` | TEXT NULL | |
| `billing_period_start` | DATE | |
| `billing_period_end` | DATE | |
| `created_at` | DATETIME | |
| `updated_at` | DATETIME | |
| `deleted_at` | DATETIME NULL | Soft delete |

**Indexes:** `ownership_id`, `reading_date`
**Validation:** Backend enforces `current_reading >= previous_reading`.
**V1 Decision — Meter number uniqueness:** `meter_number` is unique per ownership only.
The same meter identifier may exist under a different ownership. No system-wide uniqueness constraint.

---

### Table: `billing_items`

**Purpose:** Stores generated billing charges per ownership per period, before invoicing.

| Field | Type | Notes |
|---|---|---|
| `id` | BIGINT PK AI | |
| `ownership_id` | BIGINT FK → ownerships | |
| `billing_type` | ENUM('ipl','water','electricity','other') | |
| `billing_period_start` | DATE | |
| `billing_period_end` | DATE | |
| `meter_reading_id` | BIGINT FK NULL → meter_readings | Water only |
| `description` | TEXT | |
| `quantity` | DECIMAL(10,2) | Area m², usage m³, or 1 for lump sum |
| `unit` | VARCHAR(50) | m², m³, ls, unit |
| `unit_price` | DECIMAL(15,2) | Rate used at billing time |
| `subtotal` | DECIMAL(15,2) | quantity × unit_price |
| `management_fee_rate` | DECIMAL(5,2) NULL | Electricity: configurable % |
| `management_fee_amount` | DECIMAL(15,2) NULL | Electricity: computed fee |
| `pln_charge` | DECIMAL(15,2) NULL | Electricity: external PLN cost |
| `apply_tax` | TINYINT(1) DEFAULT 1 | Whether this item is included in taxable subtotal |
| `notes` | TEXT NULL | |
| `status` | ENUM('draft','invoiced') | Prevents double-invoicing |
| `created_at` | DATETIME | |
| `updated_at` | DATETIME | |
| `deleted_at` | DATETIME NULL | Soft delete |

**V1 Decision:** Water billing produces one `billing_items` row per billing period
(total water charge). Tier-level detail is preserved separately in `billing_item_tiers`
for audit and invoice display. The invoice preview may show tier rows when applicable.

---

### Table: `billing_item_tiers`

**Purpose:** Preserves the tier-by-tier water calculation breakdown for audit and transparency.

| Field | Type | Notes |
|---|---|---|
| `id` | BIGINT PK AI | |
| `billing_item_id` | BIGINT FK → billing_items | |
| `tier_label` | VARCHAR(100) | e.g. "0–20 m³" |
| `usage_in_tier` | DECIMAL(10,2) | m³ consumed in this tier |
| `rate` | DECIMAL(15,2) | Rate per m³ for this tier |
| `amount` | DECIMAL(15,2) | usage_in_tier × rate |
| `created_at` | DATETIME | |

---

### Table: `invoices`

**Purpose:** A formal billing document issued to a customer. Never physically deleted.

| Field | Type | Notes |
|---|---|---|
| `id` | BIGINT PK AI | |
| `invoice_number` | VARCHAR(50) UNIQUE | System-generated |
| `company_id` | BIGINT FK → companies | |
| `customer_id` | BIGINT FK → customers | |
| `ownership_id` | BIGINT FK → ownerships | Required in V1. One invoice = one ownership. |
| `invoice_type` | ENUM('separate','combined') | |
| `invoice_date` | DATE | |
| `due_date` | DATE | Computed: invoice_date + invoice_due_date_offset_days from system_settings |
| `billing_period_start` | DATE | Earliest period start among included billing_items |
| `billing_period_end` | DATE | Latest period end among included billing_items. May span multiple periods when admin explicitly selects items from different periods. Each invoice_item preserves its own original billing period. |
| `billing_address` | TEXT | Snapshot at invoice time |
| `subtotal_dpp` | DECIMAL(15,2) | Sum of invoice_items.subtotal where apply_tax = 1 |
| `non_taxable_subtotal` | DECIMAL(15,2) DEFAULT 0 | Sum of invoice_items.subtotal where apply_tax = 0 |
| `dpp_nilai_lain` | DECIMAL(15,2) | (11/12) × subtotal_dpp |
| `ppn_amount` | DECIMAL(15,2) | 12% × dpp_nilai_lain |
| `total_amount` | DECIMAL(15,2) | dpp_nilai_lain + ppn_amount + non_taxable_subtotal |
| `terbilang` | TEXT | Indonesian number-to-words of total |
| `status` | ENUM('draft','issued','unpaid','partially_paid','paid','cancelled') | |
| `signature_id` | BIGINT FK NULL → signatures | |
| `notes` | TEXT NULL | |
| `pdf_path` | VARCHAR(255) NULL | Server path to generated PDF |
| `created_by` | BIGINT FK → users | |
| `created_at` | DATETIME | |
| `updated_at` | DATETIME | |
| `deleted_at` | DATETIME NULL | Status-only; record never purged |

**Indexes:** `invoice_number`, `customer_id`, `status`

---

### Table: `invoice_items`

**Purpose:** Line-item snapshot of billing charges as they appear on the invoice.
Decoupled from `billing_items` so the invoice is preserved even if billing data changes.
Each row retains its own billing period — one invoice may span multiple periods.

| Field | Type | Notes |
|---|---|---|
| `id` | BIGINT PK AI | |
| `invoice_id` | BIGINT FK → invoices | |
| `billing_item_id` | BIGINT FK NULL → billing_items | Traceability link |
| `description` | TEXT | |
| `quantity` | DECIMAL(10,2) | |
| `unit` | VARCHAR(50) | |
| `unit_price` | DECIMAL(15,2) | |
| `subtotal` | DECIMAL(15,2) | |
| `apply_tax` | TINYINT(1) DEFAULT 1 | Copied from billing_items; controls tax inclusion |
| `billing_period_start` | DATE NULL | Preserved from source billing_item |
| `billing_period_end` | DATE NULL | Preserved from source billing_item |
| `sort_order` | INT DEFAULT 0 | Controls display order on invoice |
| `created_at` | DATETIME | |
| `updated_at` | DATETIME | |

---

### Table: `payments`

**Purpose:** Records external payments made against an invoice. Never physically deleted.

| Field | Type | Notes |
|---|---|---|
| `id` | BIGINT PK AI | |
| `invoice_id` | BIGINT FK → invoices | |
| `payment_date` | DATE | |
| `amount` | DECIMAL(15,2) | |
| `payment_method` | VARCHAR(100) | e.g. Transfer, Cash, Cek |
| `reference_number` | VARCHAR(100) NULL | Bank ref or cheque number |
| `proof_path` | VARCHAR(255) NULL | Optional uploaded proof |
| `notes` | TEXT NULL | |
| `recorded_by` | BIGINT FK → users | |
| `created_at` | DATETIME | |
| `updated_at` | DATETIME | |

**Note:** No soft delete. Payment history must be fully preserved.

---

### Table: `receipts`

**Purpose:** Official receipt document generated after a payment is recorded.

| Field | Type | Notes |
|---|---|---|
| `id` | BIGINT PK AI | |
| `receipt_number` | VARCHAR(50) UNIQUE | System-generated |
| `payment_id` | BIGINT FK → payments | |
| `invoice_id` | BIGINT FK → invoices | |
| `customer_id` | BIGINT FK → customers | |
| `company_id` | BIGINT FK → companies | |
| `receipt_date` | DATE | |
| `amount` | DECIMAL(15,2) | |
| `terbilang` | TEXT | |
| `description` | TEXT | |
| `signature_id` | BIGINT FK NULL → signatures | |
| `pdf_path` | VARCHAR(255) NULL | |
| `created_by` | BIGINT FK → users | |
| `created_at` | DATETIME | |
| `updated_at` | DATETIME | |

**V1 Decision:** A receipt is generated for each recorded payment, including partial payments.
Receipt number format: `KWT/YYYY/MM/NNNN`, sequential per month, reset monthly.

---

### Table: `signatures`

**Purpose:** Stores admin and leadership signature images used on invoices and receipts.

| Field | Type | Notes |
|---|---|---|
| `id` | BIGINT PK AI | |
| `company_id` | BIGINT FK → companies | |
| `label` | VARCHAR(100) | e.g. "Admin", "Direktur" |
| `name` | VARCHAR(255) | Printed name below signature |
| `position` | VARCHAR(100) | Printed title |
| `signature_path` | VARCHAR(255) NULL | Image file path |
| `is_active` | TINYINT(1) DEFAULT 1 | |
| `created_at` | DATETIME | |
| `updated_at` | DATETIME | |
| `deleted_at` | DATETIME NULL | Soft delete |

---

### Table: `users`

**Purpose:** Application user accounts (Developer and Admin roles).

| Field | Type | Notes |
|---|---|---|
| `id` | BIGINT PK AI | |
| `name` | VARCHAR(255) | |
| `email` | VARCHAR(150) UNIQUE | |
| `password` | VARCHAR(255) | Bcrypt hashed |
| `role_id` | BIGINT FK → roles | |
| `is_active` | TINYINT(1) DEFAULT 1 | Developer can disable admin accounts |
| `last_login_at` | DATETIME NULL | |
| `created_at` | DATETIME | |
| `updated_at` | DATETIME | |
| `deleted_at` | DATETIME NULL | Soft delete |

---

### Table: `roles`

**Purpose:** User role definitions.

| Field | Type | Notes |
|---|---|---|
| `id` | BIGINT PK AI | |
| `name` | VARCHAR(100) | 'developer', 'admin' |
| `description` | TEXT NULL | |
| `created_at` | DATETIME | |
| `updated_at` | DATETIME | |

---

### Table: `menu_permissions`

**Purpose:** Controls which menus and actions each role can access.

| Field | Type | Notes |
|---|---|---|
| `id` | BIGINT PK AI | |
| `role_id` | BIGINT FK → roles | |
| `menu_key` | VARCHAR(100) | e.g. 'billing.invoice', 'settings.users' |
| `can_view` | TINYINT(1) DEFAULT 0 | |
| `can_create` | TINYINT(1) DEFAULT 0 | |
| `can_edit` | TINYINT(1) DEFAULT 0 | |
| `can_delete` | TINYINT(1) DEFAULT 0 | |
| `created_at` | DATETIME | |
| `updated_at` | DATETIME | |

---

### Table: `whatsapp_logs`

**Purpose:** Records every WhatsApp send attempt with provider response and status.

| Field | Type | Notes |
|---|---|---|
| `id` | BIGINT PK AI | |
| `invoice_id` | BIGINT FK → invoices | |
| `customer_id` | BIGINT FK → customers | |
| `phone_number` | VARCHAR(50) | Number used at time of send |
| `message_preview` | TEXT | |
| `pdf_path` | VARCHAR(255) NULL | |
| `provider` | VARCHAR(50) | 'mock' or 'fonnte' |
| `status` | ENUM('pending','success','failed') | |
| `response_payload` | JSON NULL | Raw provider response |
| `sent_at` | DATETIME NULL | |
| `created_by` | BIGINT FK → users | |
| `created_at` | DATETIME | |

---

### Table: `system_settings`

**Purpose:** Stores configurable system-wide defaults, including the due-date offset.

| Field | Type | Notes |
|---|---|---|
| `id` | BIGINT PK AI | |
| `key` | VARCHAR(100) UNIQUE | e.g. `invoice_due_date_offset_days` |
| `value` | VARCHAR(255) | Stored as string; cast to correct type by service |
| `description` | TEXT NULL | Human-readable explanation |
| `created_at` | DATETIME | |
| `updated_at` | DATETIME | |

**V1 Seed values:**

| key | value | description |
|---|---|---|
| `invoice_due_date_offset_days` | `14` | Default days after invoice_date for due_date |

**Note:** `company_id` is intentionally kept in the schema for future multi-company
extensibility. In V1, only one company record is active. The company is selected by
the active session/configuration, not per-request.

---

### Complete Table List Summary

| # | Table | Soft Delete | Notes |
|---|---|---|---|
| 1 | `companies` | Yes | |
| 2 | `customers` | Yes | |
| 3 | `pics` | Yes | |
| 4 | `projects` | Yes | |
| 5 | `clusters` | Yes | Residential only |
| 6 | `blocks` | Yes | Residential only |
| 7 | `lots` | Yes | Residential only |
| 8 | `ipl_rates` | Yes | |
| 9 | `water_rate_groups` | Yes | |
| 10 | `water_rate_tiers` | No | Replaced when group is updated |
| 11 | `tax_configurations` | No | Only one active record |
| 12 | `ownerships` | Yes | |
| 13 | `meter_readings` | Yes | |
| 14 | `billing_items` | Yes | |
| 15 | `billing_item_tiers` | No | Audit only |
| 16 | `invoices` | Yes (status only) | Never purged |
| 17 | `invoice_items` | No | Snapshot; no delete |
| 18 | `payments` | No | Never purged |
| 19 | `receipts` | No | Never purged |
| 20 | `signatures` | Yes | |
| 21 | `users` | Yes | |
| 22 | `roles` | No | Seeded |
| 23 | `menu_permissions` | No | Seeded |
| 24 | `whatsapp_logs` | No | Append-only log |
| 25 | `system_settings` | No | Seeded with defaults (e.g. due-date offset = 14) |

---

## 4. Business Logic

All calculations are performed exclusively in the backend Service layer.
React never computes monetary values.

---

### 4.1 IPL Calculation

```
IPL Subtotal = ownership.area (m²) × ipl_rates.rate_per_sqm (Rp/m²)
```

Rate is pulled from `ownerships.ipl_rate_id → ipl_rates.rate_per_sqm`.
Special pricing (e.g. 50% discount) is stored as a separate `ipl_rates` record — not
computed as a multiplier at billing time.

---

### 4.2 Water Meter Usage

```
usage = current_reading - previous_reading
```

Stored in `meter_readings.usage`. Backend validates `current_reading >= previous_reading`
before saving.

---

### 4.3 Progressive Water Pricing (WaterBillingService)

The service iterates tiers ordered by `min_usage ASC`:

```
remaining_usage = total_usage
total_cost = 0

for each tier (ascending order):
    tier_width = tier.max_usage - tier.min_usage  (NULL max = unlimited)
    usage_in_tier = min(remaining_usage, tier_width)
    tier_cost = usage_in_tier × tier.rate_per_m3
    total_cost += tier_cost
    remaining_usage -= usage_in_tier
    if remaining_usage <= 0: break

water_usage_cost = total_cost
```

Each tier result is saved to `billing_item_tiers` for audit.

**Example:**
Usage = 24.58 m³, Tiers: 0–20 @ Rp7,500 | 21–40 @ Rp8,500
```
Tier 1: 20 × 7,500 = 150,000
Tier 2: 4.58 × 8,500 = 38,930
Total usage cost = 188,930
```

---

### 4.4 Water Abonemen

```
water_subtotal = abonemen + water_usage_cost
```

`abonemen` value comes from `water_rate_groups.abonemen`. If 0, no fixed charge applies.

---

### 4.5 Electricity Charges

```
management_fee = pln_charge × (management_fee_rate / 100)
electricity_subtotal = pln_charge + management_fee
```

`management_fee_rate` is stored on the `billing_items` row at generation time (not
hardcoded). The rate is configurable and entered by the admin when recording the charge.

---

### 4.6 Other Charges

Manual entry. Admin inputs `amount` directly. `apply_tax` flag determines whether
PPN is included when invoiced.

---

### 4.7 Tax Calculation (TaxService — backend only)

Source: active `tax_configurations` record. All values read from DB — never hardcoded.

#### Taxable vs Non-Taxable Items

Each `invoice_items` row carries an `apply_tax` flag (copied from the source `billing_items` row).

| Item type | apply_tax | Included in subtotal_dpp? | Subject to DPP Nilai Lain and PPN? |
|---|---|---|---|
| IPL | 1 (default) | Yes | Yes |
| Water | 1 (default) | Yes | Yes |
| Electricity | 1 (default) | Yes | Yes |
| Other charges | configurable | Only if apply_tax = 1 | Only if apply_tax = 1 |

#### Invoice Tax Calculation

```
subtotal_dpp        = SUM(invoice_items.subtotal)  WHERE apply_tax = 1
non_taxable_subtotal = SUM(invoice_items.subtotal)  WHERE apply_tax = 0

dpp_nilai_lain      = (numerator / denominator) × subtotal_dpp
                    = (11 / 12) × subtotal_dpp

ppn_amount          = ppn_rate × dpp_nilai_lain
                    = 0.12 × dpp_nilai_lain

total_amount        = dpp_nilai_lain + ppn_amount + non_taxable_subtotal
```

**Worked example (all items taxable, from BUSINESS_RULES.md):**
```
subtotal_dpp     = 855,000
dpp_nilai_lain   = (11/12) × 855,000 = 783,750
ppn_amount       = 0.12 × 783,750    = 94,050
non_taxable      = 0
total_amount     = 783,750 + 94,050 + 0 = 877,800
```

**Worked example (mixed taxable and non-taxable):**
```
subtotal_dpp       = 800,000  (IPL + Water — taxable)
non_taxable        = 55,000   (one Other Charge with apply_tax = 0)
dpp_nilai_lain     = (11/12) × 800,000 = 733,333
ppn_amount         = 0.12 × 733,333    = 88,000
total_amount       = 733,333 + 88,000 + 55,000 = 876,333
```

Tax is always computed **once at invoice level**, never line-by-line, to avoid rounding drift.

---

### 4.8 Invoice Totals

```
subtotal_dpp         = SUM(invoice_items.subtotal) WHERE apply_tax = 1
non_taxable_subtotal = SUM(invoice_items.subtotal) WHERE apply_tax = 0
→ TaxService computes dpp_nilai_lain, ppn_amount, total_amount (see 4.7)
terbilang            = TerbilangService(total_amount) → Indonesian number-to-words
```

All three amounts (`subtotal_dpp`, `non_taxable_subtotal`, `total_amount`) are stored
on the `invoices` row as a snapshot at generation time.

---

### 4.9 Payment Status Logic (InvoiceService)

Recalculated on every payment save or update:

```
total_paid = SUM(payments.amount) WHERE invoice_id = X

if total_paid == 0:                   status = 'unpaid'
if 0 < total_paid < total_amount:     status = 'partially_paid'
if total_paid >= total_amount:        status = 'paid'
```

---

### 4.10 Invoice Number Generation

**Finalized format:** `INV/YYYY/MM/NNNN`

- `YYYY` = 4-digit year, `MM` = 2-digit month, `NNNN` = sequential counter zero-padded to 4 digits
- Counter resets to 0001 each calendar month
- Example: `INV/2026/07/0001`, `INV/2026/07/0002`, `INV/2026/08/0001`
- Generated inside a DB transaction with `SELECT FOR UPDATE` on a sequence counter to prevent duplicates under concurrent requests

---

### 4.11 Receipt Number Generation

**Finalized format:** `KWT/YYYY/MM/NNNN`

- `YYYY` = 4-digit year, `MM` = 2-digit month, `NNNN` = sequential counter zero-padded to 4 digits
- Counter resets to 0001 each calendar month
- Example: `KWT/2026/07/0001`, `KWT/2026/07/0002`, `KWT/2026/08/0001`
- Same atomic generation logic as invoice numbers (DB transaction + `SELECT FOR UPDATE`)
- One receipt is generated per payment recording, including partial payments

---

### 4.12 Duplicate Billing Prevention

Before generating a new `billing_items` row, `BillingService` checks:

```
WHERE ownership_id = X
AND billing_type = Y
AND billing_period_start = Z
AND status != 'cancelled'
AND deleted_at IS NULL
```

If a record already exists, reject and return an error.

---

### 4.13 Dashboard Outstanding Aging

**Finalized rule:** Outstanding invoice age is calculated from `invoices.due_date`, not `invoices.invoice_date`.

An invoice is considered outstanding when `status IN ('issued', 'unpaid', 'partially_paid')`.

Age buckets:

| Bucket | Condition |
|---|---|
| 1–3 months | DATEDIFF(TODAY, due_date) BETWEEN 1 AND 90 |
| 4–6 months | DATEDIFF(TODAY, due_date) BETWEEN 91 AND 180 |
| 7–12 months | DATEDIFF(TODAY, due_date) BETWEEN 181 AND 365 |
| >12 months | DATEDIFF(TODAY, due_date) > 365 |

Dashboard API (`GET /api/v1/dashboard/outstanding`) returns the sum of `total_amount`
for each bucket based on the above conditions. All values come from live DB queries.

---

## 5. REST API Structure

All endpoints use prefix `/api/v1/`.
All responses follow the envelope: `{ "success": bool, "data": ..., "message": "...", "errors": ... }`.
All routes except `auth/login` require a valid JWT Bearer token.

---

### Authentication

```
POST   /api/v1/auth/login          → returns JWT token + user info
POST   /api/v1/auth/logout         → invalidates token
GET    /api/v1/auth/me             → returns authenticated user + role
POST   /api/v1/auth/refresh        → refresh JWT
```

### Users & Roles

```
GET    /api/v1/users
POST   /api/v1/users
GET    /api/v1/users/{id}
PUT    /api/v1/users/{id}
DELETE /api/v1/users/{id}
GET    /api/v1/roles
GET    /api/v1/roles/{id}/permissions
PUT    /api/v1/roles/{id}/permissions
```

### Company

```
GET    /api/v1/companies
POST   /api/v1/companies
GET    /api/v1/companies/{id}
PUT    /api/v1/companies/{id}
DELETE /api/v1/companies/{id}
```

### Customers & PICs

```
GET    /api/v1/customers               ?search=&type=&page=
POST   /api/v1/customers
GET    /api/v1/customers/{id}
PUT    /api/v1/customers/{id}
DELETE /api/v1/customers/{id}
GET    /api/v1/customers/{id}/pics
POST   /api/v1/customers/{id}/pics
PUT    /api/v1/customers/{id}/pics/{picId}
DELETE /api/v1/customers/{id}/pics/{picId}
```

### Projects / Clusters / Blocks / Lots

```
GET    /api/v1/projects                ?type=residential|commercial
POST   /api/v1/projects
GET    /api/v1/projects/{id}
PUT    /api/v1/projects/{id}
DELETE /api/v1/projects/{id}

GET    /api/v1/projects/{id}/clusters
POST   /api/v1/projects/{id}/clusters
PUT    /api/v1/clusters/{id}
DELETE /api/v1/clusters/{id}

GET    /api/v1/clusters/{id}/blocks
POST   /api/v1/clusters/{id}/blocks
PUT    /api/v1/blocks/{id}
DELETE /api/v1/blocks/{id}

GET    /api/v1/blocks/{id}/lots
POST   /api/v1/blocks/{id}/lots
PUT    /api/v1/lots/{id}
DELETE /api/v1/lots/{id}
```

### Pricing Configuration

```
GET    /api/v1/ipl-rates              ?project_id=
POST   /api/v1/ipl-rates
PUT    /api/v1/ipl-rates/{id}
DELETE /api/v1/ipl-rates/{id}

GET    /api/v1/water-rate-groups      ?project_id=
POST   /api/v1/water-rate-groups
PUT    /api/v1/water-rate-groups/{id}
DELETE /api/v1/water-rate-groups/{id}
GET    /api/v1/water-rate-groups/{id}/tiers
POST   /api/v1/water-rate-groups/{id}/tiers
PUT    /api/v1/water-rate-tiers/{id}
DELETE /api/v1/water-rate-tiers/{id}

GET    /api/v1/tax-configurations
POST   /api/v1/tax-configurations
PUT    /api/v1/tax-configurations/{id}/activate
```

### Signatures

```
GET    /api/v1/signatures
POST   /api/v1/signatures
PUT    /api/v1/signatures/{id}
DELETE /api/v1/signatures/{id}
```

### Ownership

```
GET    /api/v1/ownerships             ?customer_id=&project_id=
POST   /api/v1/ownerships
GET    /api/v1/ownerships/{id}
PUT    /api/v1/ownerships/{id}
DELETE /api/v1/ownerships/{id}
```

### Meter Readings

```
GET    /api/v1/meter-readings         ?ownership_id=&period=
POST   /api/v1/meter-readings
GET    /api/v1/meter-readings/{id}
PUT    /api/v1/meter-readings/{id}
DELETE /api/v1/meter-readings/{id}
GET    /api/v1/ownerships/{id}/meter-readings
GET    /api/v1/ownerships/{id}/meter-readings/latest
```

### Billing

```
POST   /api/v1/billing/generate-ipl         → creates billing_items row(s)
POST   /api/v1/billing/generate-water       → creates billing_items + billing_item_tiers
POST   /api/v1/billing/generate-electricity → creates billing_items row
POST   /api/v1/billing/generate-other       → creates billing_items row
GET    /api/v1/billing-items               ?ownership_id=&status=&period=
GET    /api/v1/billing-items/{id}
PUT    /api/v1/billing-items/{id}
DELETE /api/v1/billing-items/{id}
POST   /api/v1/billing/calculate-tax        → preview only, no DB write
```

### Invoice

```
GET    /api/v1/invoices               ?customer_id=&status=&period=&page=
POST   /api/v1/invoices
GET    /api/v1/invoices/{id}
PUT    /api/v1/invoices/{id}
DELETE /api/v1/invoices/{id}
POST   /api/v1/invoices/{id}/issue
POST   /api/v1/invoices/{id}/cancel
GET    /api/v1/invoices/{id}/pdf      → generate or serve existing PDF
POST   /api/v1/invoices/{id}/whatsapp → trigger WhatsApp send
GET    /api/v1/invoices/{id}/whatsapp-logs
```

### Payment

```
GET    /api/v1/payments               ?invoice_id=&date_from=&date_to=
POST   /api/v1/payments
GET    /api/v1/payments/{id}
PUT    /api/v1/payments/{id}
GET    /api/v1/invoices/{id}/payments
```

### Receipts

```
GET    /api/v1/receipts               ?customer_id=&date_from=&date_to=
GET    /api/v1/receipts/{id}
GET    /api/v1/receipts/{id}/pdf
GET    /api/v1/payments/{id}/receipt
```

### Reports

```
GET    /api/v1/reports/periodic-billing          ?type=&date_from=&date_to=&customer_id=&project_id=
GET    /api/v1/reports/payment-receipts          ?date_from=&date_to=&customer_id=
GET    /api/v1/reports/periodic-billing/export-excel
GET    /api/v1/reports/payment-receipts/export-excel
```

### Dashboard

```
GET    /api/v1/dashboard/summary         → monthly + yearly received totals
GET    /api/v1/dashboard/outstanding     → aging buckets (1–3, 4–6, 7–12, >12 months)
GET    /api/v1/dashboard/recent-invoices
GET    /api/v1/dashboard/recent-payments
```

---

## 6. Frontend Architecture

### Folder Structure

```
frontend/
├── public/
├── src/
│   ├── api/                    # Axios instance + domain API modules
│   │   ├── axiosClient.js      # Base URL, JWT interceptor, 401 handler
│   │   ├── authApi.js
│   │   ├── customerApi.js
│   │   ├── projectApi.js
│   │   ├── ownershipApi.js
│   │   ├── meterApi.js
│   │   ├── billingApi.js
│   │   ├── invoiceApi.js
│   │   ├── paymentApi.js
│   │   ├── receiptApi.js
│   │   ├── reportApi.js
│   │   └── dashboardApi.js
│   ├── assets/                 # Static images, fonts
│   ├── components/
│   │   ├── common/             # Button, Input, Select, Modal, Table,
│   │   │                       # Pagination, Badge, Alert, Spinner
│   │   ├── forms/              # Reusable form field wrappers with RHF
│   │   ├── layout/             # Sidebar, Header, PageWrapper, Breadcrumb
│   │   └── pdf/                # PDF embed/preview component
│   ├── hooks/                  # useAuth, useDebounce, useToast, etc.
│   ├── pages/
│   │   ├── auth/               # Login
│   │   ├── dashboard/
│   │   ├── master/
│   │   │   ├── company/
│   │   │   ├── customer/
│   │   │   ├── project/
│   │   │   ├── cluster/
│   │   │   ├── block/
│   │   │   ├── lot/
│   │   │   ├── ipl-rates/
│   │   │   ├── water-rates/
│   │   │   └── tax-config/
│   │   ├── ownership/
│   │   ├── meter/
│   │   ├── billing/
│   │   ├── invoice/
│   │   ├── payment/
│   │   ├── receipt/
│   │   ├── reports/
│   │   └── settings/           # Users, Roles, Signatures
│   ├── store/
│   │   ├── authStore.js        # Zustand: token, user, role
│   │   └── uiStore.js          # Zustand: sidebar state, theme
│   ├── utils/
│   │   ├── currency.js         # Rp formatter
│   │   ├── date.js             # Date formatting (Indonesian locale)
│   │   └── terbilang.js        # Display-only; actual value from backend
│   ├── router/
│   │   ├── index.jsx           # React Router v6 route definitions
│   │   ├── AuthGuard.jsx       # Redirect if not authenticated
│   │   └── RoleGuard.jsx       # Restrict by role
│   ├── App.jsx
│   └── main.jsx
├── .env.example
├── vite.config.js
└── package.json
```

### State Management

| Tool | Purpose |
|---|---|
| **Zustand** | Auth session (token, user, role), UI state (sidebar, notifications) |
| **TanStack Query** | All server data: fetching, caching, background refresh, invalidation |
| **useState** | Form inputs and local UI-only state |

TanStack Query handles all API data. Zustand is only for client-side global state.

### API Communication

- Single `axiosClient.js` sets base URL from `.env`, attaches `Authorization: Bearer {token}`
  header via request interceptor, and triggers logout on 401 responses
- Domain API files (`invoiceApi.js`, etc.) export named async functions
- TanStack Query `useQuery` / `useMutation` wraps all API calls

### Form Validation

- **React Hook Form** manages form state and submission
- **Zod** schemas define validation rules (required, min/max, type)
- Errors shown inline under each field
- Backend always re-validates — frontend validation is UX-only

### Page Structure Pattern

Every major entity follows this consistent structure:

```
/entity              → ListPage    (table, search, filter, Tambah button)
/entity/new          → FormPage    (create form)
/entity/:id/edit     → FormPage    (edit form, pre-populated)
/entity/:id          → DetailPage  (read-only, action buttons)
```

### UI Design Direction

- Language: **Indonesian** (Tambah, Simpan, Ubah, Hapus, Cari, Filter, Generate, Preview)
- Colors: muted deep green, sage, warm off-white, dark charcoal, soft gray
- Typography: clean, professional, minimal decoration
- No neon colors, no excessive gradients, no glassmorphism
- Target user: non-technical admin staff — clarity over cleverness

---

## 7. Backend Architecture

### CodeIgniter 4 Folder Structure

```
backend/
├── app/
│   ├── Controllers/
│   │   └── Api/
│   │       ├── AuthController.php
│   │       ├── UserController.php
│   │       ├── CompanyController.php
│   │       ├── CustomerController.php
│   │       ├── ProjectController.php
│   │       ├── OwnershipController.php
│   │       ├── MeterReadingController.php
│   │       ├── BillingController.php
│   │       ├── InvoiceController.php
│   │       ├── PaymentController.php
│   │       ├── ReceiptController.php
│   │       ├── ReportController.php
│   │       ├── DashboardController.php
│   │       └── SignatureController.php
│   ├── Services/
│   │   ├── BillingService.php          # Orchestrates billing generation
│   │   ├── IplBillingService.php
│   │   ├── WaterBillingService.php     # Progressive tier calculation
│   │   ├── ElectricityBillingService.php
│   │   ├── TaxService.php              # DPP Nilai Lain + PPN
│   │   ├── InvoiceService.php          # Invoice creation, status logic
│   │   ├── PaymentService.php          # Payment recording, status update
│   │   ├── ReceiptService.php
│   │   ├── PdfService.php              # DomPDF wrapper
│   │   ├── TerbilangService.php        # Number to Indonesian words
│   │   ├── NumberGeneratorService.php  # Invoice/receipt number sequences
│   │   ├── WhatsAppService.php         # Provider orchestrator
│   │   └── Providers/
│   │       ├── WhatsAppProviderInterface.php
│   │       ├── MockWhatsAppProvider.php
│   │       └── FonnteWhatsAppProvider.php   ← stub only, not active
│   ├── Models/
│   │   ├── CompanyModel.php
│   │   ├── CustomerModel.php
│   │   ├── PicModel.php
│   │   ├── ProjectModel.php
│   │   ├── ClusterModel.php
│   │   ├── BlockModel.php
│   │   ├── LotModel.php
│   │   ├── IplRateModel.php
│   │   ├── WaterRateGroupModel.php
│   │   ├── WaterRateTierModel.php
│   │   ├── TaxConfigurationModel.php
│   │   ├── OwnershipModel.php
│   │   ├── MeterReadingModel.php
│   │   ├── BillingItemModel.php
│   │   ├── BillingItemTierModel.php
│   │   ├── InvoiceModel.php
│   │   ├── InvoiceItemModel.php
│   │   ├── PaymentModel.php
│   │   ├── ReceiptModel.php
│   │   ├── SignatureModel.php
│   │   ├── UserModel.php
│   │   ├── RoleModel.php
│   │   ├── MenuPermissionModel.php
│   │   └── WhatsAppLogModel.php
│   ├── Filters/
│   │   ├── AuthFilter.php              # Validates JWT on all protected routes
│   │   └── RoleFilter.php             # Checks menu_permissions for role
│   ├── Validation/
│   │   └── BillingValidation.php       # Custom rules (e.g. tier continuity)
│   ├── Config/
│   │   ├── Routes.php                  # All /api/v1/ route definitions
│   │   └── Services.php               # Binds WhatsAppProvider implementation
│   └── Database/
│       ├── Migrations/                 # One file per table
│       └── Seeds/                      # Roles, default developer user, tax config
├── app/Views/
│   └── pdf/
│       ├── invoice.php                 # DomPDF invoice HTML template
│       └── receipt.php                 # DomPDF receipt HTML template
├── writable/
│   ├── uploads/
│   │   ├── meter-photos/
│   │   ├── pdfs/invoices/
│   │   ├── pdfs/receipts/
│   │   ├── payment-proofs/
│   │   └── signatures/
│   ├── cache/
│   ├── logs/
│   └── session/
├── public/
└── .env
```

### Layer Responsibilities

| Layer | Responsibility |
|---|---|
| **Controller** | Parse request, validate input format, call Service, return JSON |
| **Service** | All business logic, calculations, orchestration across multiple Models |
| **Model** | DB queries only — SELECT, INSERT, UPDATE, soft delete via `deleted_at` |
| **Filter** | JWT auth verification, role/permission enforcement per route group |

**Controllers never contain business logic. Services never directly use `$_POST` or request objects.**

### PDF Generation

`PdfService` wraps **DomPDF** (PHP). Templates are CI4 Views (`app/Views/pdf/`).
PDFs are saved to `writable/uploads/pdfs/` and the path stored in `invoices.pdf_path`
and `receipts.pdf_path`. PDF is regenerated on demand if file is missing.

### WhatsApp Provider Architecture (detailed)

```php
// WhatsAppProviderInterface.php
interface WhatsAppProviderInterface {
    public function send(string $phone, string $message, ?string $pdfPath): array;
    // Returns: ['success' => bool, 'message' => string, 'payload' => array]
}

// MockWhatsAppProvider.php
class MockWhatsAppProvider implements WhatsAppProviderInterface {
    public function send(...): array {
        // Randomly returns success or failure for demo purposes
        // Logs result to whatsapp_logs
    }
}

// FonnteWhatsAppProvider.php  ← stub only, not used in prototype
class FonnteWhatsAppProvider implements WhatsAppProviderInterface {
    public function send(...): array {
        // TODO: Implement Fonnte HTTP API call
        throw new \RuntimeException('Fonnte provider not yet implemented.');
    }
}

// WhatsAppService.php
class WhatsAppService {
    private WhatsAppProviderInterface $provider;
    public function __construct(WhatsAppProviderInterface $provider) {
        $this->provider = $provider;
    }
    public function sendInvoice(Invoice $invoice): array {
        // Builds message, resolves PDF path, calls provider->send()
        // Saves result to whatsapp_logs
    }
}

// Config/Services.php — binding (change this one line to switch to Fonnte later)
$services->whatsApp = new WhatsAppService(new MockWhatsAppProvider());
```

### WhatsApp Prototype Message Template

`WhatsAppService.sendInvoice()` constructs the message using this Indonesian template:

```
Yth. Bapak/Ibu {customer_name},

Berikut kami kirimkan invoice {invoice_number} untuk periode {billing_period}.
Total tagihan: {total_amount}
Jatuh tempo: {due_date}

Invoice terlampir dalam format PDF.

Terima kasih.
```

Variable mapping:

| Placeholder | Source |
|---|---|
| `{customer_name}` | `customers.name` |
| `{invoice_number}` | `invoices.invoice_number` |
| `{billing_period}` | Formatted as `billing_period_start – billing_period_end` |
| `{total_amount}` | `invoices.total_amount` formatted as Rp |
| `{due_date}` | `invoices.due_date` formatted as DD MMMM YYYY |

The mock provider logs this message preview to `whatsapp_logs.message_preview`.
Fonnte integration (future) will use the same template without changes to the invoice flow.

---

## 8. Module Dependencies

Each module depends on the one(s) above it. Do not implement a module until its
dependencies are complete and tested.

```
[1] Auth + Users + Roles + Menu Permissions
        ↓
[2] Master Data
    ├── Company
    ├── Customer → PIC
    ├── Project → Cluster → Block → Lot
    ├── IPL Rates
    ├── Water Rate Groups → Water Rate Tiers
    ├── Tax Configuration (active record)
    └── Signatures
        ↓
[3] Ownership
    (requires: Customer, Project, IPL Rate, Water Rate Group)
        ↓
[4] Meter Reading
    (requires: Ownership)
        ↓
[5] Billing Engine
    ├── IPL Billing        (requires: Ownership + IPL Rate)
    ├── Water Billing      (requires: Ownership + Meter Reading + Water Rate Group/Tiers)
    ├── Electricity Billing (requires: Ownership)
    └── Other Charges      (requires: Ownership/Customer)
        ↓
[6] Tax Calculation
    (requires: Billing Items + active Tax Configuration)
        ↓
[7] Invoice
    (requires: Billing Items, Tax, Customer, Company, Signature)
        ↓
[8] PDF Generation
    (requires: Invoice with all items populated)
        ↓
[9] Mock WhatsApp
    (requires: PDF path, Customer phone number, Invoice)
        ↓
[10] Payment Recording
    (requires: Invoice in 'issued' or 'unpaid' status)
        ↓
[11] Receipt Generation
    (requires: Payment, Invoice, Customer, Company, Signature)
        ↓
[12] Reports + Dashboard
    (requires: all of the above)
```

---

## 9. Main Data Flows

### Flow A — Billing → Invoice → PDF → WhatsApp

```
Admin selects Ownership
  → Backend reads: area, ipl_rate, water_rate_group, tiers
  → Admin enters current meter reading
  → MeterReadingModel saves reading; usage = current - previous
  → WaterBillingService iterates tiers → creates billing_items + billing_item_tiers
  → IplBillingService computes area × rate → creates billing_items
  → Admin reviews billing items (status: draft)
  → Admin selects items and chooses invoice type (separate / combined)
  → InvoiceService creates invoices row + invoice_items rows (snapshot)
  → TaxService computes subtotal_dpp → dpp_nilai_lain → ppn_amount → total_amount
  → TerbilangService generates terbilang text
  → billing_items.status updated to 'invoiced'
  → invoice.status = 'issued' / 'unpaid'
  → PdfService generates PDF → saved to writable/uploads/pdfs/invoices/
  → invoice.pdf_path updated
  → Admin clicks "Kirim WhatsApp"
  → InvoiceController calls WhatsAppService.sendInvoice(invoice)
  → WhatsAppService → MockWhatsAppProvider.send(phone, message, pdfPath)
  → Result (success/failure) saved to whatsapp_logs
  → UI shows confirmation and log entry
```

### Flow B — Payment Recording → Receipt → Reports

```
Admin opens invoice (status: unpaid or partially_paid)
  → Admin fills payment form: date, amount, method, optional proof
  → PaymentService.recordPayment() saves payments row
  → PaymentService recalculates total_paid for invoice
  → InvoiceService updates invoice.status:
      total_paid == 0           → unpaid
      0 < total_paid < total   → partially_paid
      total_paid >= total      → paid
  → ReceiptService.generate() creates receipts row
  → receipt_number auto-generated by NumberGeneratorService
  → PdfService generates receipt PDF → saved to writable/uploads/pdfs/receipts/
  → receipt.pdf_path updated
  → Admin can preview/download receipt PDF
  → Dashboard queries automatically reflect new payment
  → Reports include new payment record
```

### Flow C — Combined Invoice Design

```
Admin selects Ownership with both IPL and Water billing_items (status: draft)
  → Admin chooses "Combined Invoice"
  → InvoiceService creates one invoices row (invoice_type = 'combined')
  → invoice_items contains:
      Row 1: IPL description, area, rate, subtotal
      Row 2: Abonemen air, 1, abonemen amount, subtotal
      Row 3: Pemakaian air tier 1, m³, rate, subtotal
      Row 4: Pemakaian air tier 2, m³, rate, subtotal (if applicable)
  → TaxService calculates tax on combined subtotal_dpp
  → One invoice total, one DPP Nilai Lain, one PPN, one grand total
```

### Flow D — Separate Invoice Design

```
Admin selects only IPL billing_item
  → Admin chooses "Separate Invoice"
  → InvoiceService creates one invoices row (invoice_type = 'separate')
  → invoice_items contains only IPL row(s)
  → TaxService calculates tax on IPL subtotal only
  → Separate invoice for Water can be created independently
```

---

## 10. Finalized Decisions & Remaining Questions

### 10.1 Finalized Prototype Decisions

The following items were confirmed and are now reflected throughout this document.
They are **not** open questions.

| # | Topic | Decision |
|---|---|---|
| 1 | **Invoice number format** | `INV/YYYY/MM/NNNN` — sequential per month, reset monthly |
| 2 | **Receipt number format** | `KWT/YYYY/MM/NNNN` — sequential per month, reset monthly |
| 3 | **Residential hierarchy** | Always Project → Cluster → Block → Lot in V1 |
| 4 | **Commercial hierarchy** | Project → Ownership only; no Cluster/Block/Lot |
| 5 | **Water billing display** | One `billing_items` row per period; tier detail preserved in `billing_item_tiers`; invoice preview may show tiers |
| 6 | **Combined/separate invoice** | Admin explicitly chooses at invoice generation time; not auto-determined by project type |
| 7 | **Multiple ownerships** | One invoice = one ownership in V1; cross-ownership invoices not supported |
| 8 | **IPL special pricing** | Stored as a dedicated `ipl_rates` record; not a UI-only multiplier |
| 9 | **Due date** | Configurable offset via `system_settings`; prototype default = 14 days after invoice date |
| 10 | **Partial payment receipts** | One receipt per payment recording, including partial payments |
| 11 | **Electricity billing** | Always associated with an ownership in V1 |
| 12 | **PPN applicability** | Per item via `apply_tax` flag; taxable and non-taxable amounts tracked separately; see section 4.7 |
| 13 | **Meter number uniqueness** | Scoped to ownership only; same number may exist under another ownership |
| 14 | **Multi-company scope** | V1 uses one active company; `company_id` kept in schema for future extensibility |
| 15 | **WhatsApp template** | Indonesian template defined in section 7; MockWhatsAppProvider for prototype |
| 16 | **Billing period per invoice** | One invoice may span multiple billing periods when admin explicitly selects items; each `invoice_items` row preserves its original period |
| 17 | **Dashboard outstanding aging** | Calculated from `invoices.due_date`; buckets: 1–3, 4–6, 7–12, >12 months |

---

### 10.2 Genuinely Unresolved Questions

The following items have **no confirmed answer yet** and must be resolved with the
supervisor before the relevant phase is implemented.
**Mark as `// TODO: NEEDS CONFIRMATION` in code if reached before resolution.**

| # | Topic | Question |
|---|---|---|
| 1 | **Invoice PDF layout** | Is there a specific visual layout, column order, or required header/footer format for the invoice PDF? Or is a clean professional layout acceptable? |
| 2 | **Receipt PDF layout** | Same question for the receipt/kwitansi PDF. |
| 3 | **Electricity management fee default** | Is there a standard default management fee rate (e.g. 15%) to pre-populate the form, or is it always entered manually per transaction? |
| 4 | **Water abonemen visibility on invoice** | Should the abonemen appear as a separate line item on the invoice, or merged into the total water charge description? |

---

## 11. Technical and Business Risks

| # | Risk | Impact | Mitigation |
|---|---|---|---|
| 1 | **Tax rule changes** | PPN rate or DPP formula may change again in the future. Hardcoding breaks all future invoices. | `tax_configurations` table; TaxService always reads from DB — never hardcoded. |
| 2 | **Historical data differences** | Old Excel data uses different rates and tax values. Import would produce wrong totals. | Never auto-import historical data; treat reference docs as reference only. |
| 3 | **Customer-specific rates** | Different customers negotiated different IPL/water rates. One global rate assumption breaks billing. | Per-ownership `ipl_rate_id` and `water_rate_group_id` references. |
| 4 | **Duplicate invoice generation** | Admin triggers invoice generation twice for same period → duplicate invoices. | Unique constraint check: one invoice per ownership per billing period per type. |
| 5 | **Duplicate payment recording** | Same payment accidentally recorded twice → invoice overpaid. | Require payment reference number; add duplicate detection in PaymentService. |
| 6 | **Invoice number collision** | Two concurrent requests generate the same invoice number. | DB transaction + `SELECT FOR UPDATE` on sequence counter table. |
| 7 | **Meter reading inconsistency** | Admin enters current reading lower than previous reading. | Backend validation rejects if `current_reading < previous_reading`. |
| 8 | **Tax rounding on combined invoices** | Applying tax per-line vs once on total produces different rounding results. | Always sum all line subtotals first, then apply tax once at invoice level. |
| 9 | **Soft delete cascades** | Soft-deleting a customer who has active invoices or ownerships. | Check for active unpaid invoices and ownerships before allowing soft delete. |
| 10 | **WhatsApp delivery failures** | With Fonnte (future): network errors, invalid numbers, rate limits. | `whatsapp_logs` tracks status; retry button in UI; Mock provider for prototype. |
| 11 | **PDF file storage growth** | Generated PDFs accumulate on the server filesystem. | Configurable storage path; define cleanup policy for draft/cancelled PDFs. |
| 12 | **Water tier gaps or overlaps** | Admin configures tiers with a gap (0–20, then 25–40; 21–24 missing). | Backend validates tier continuity and coverage before saving the group. |
| 13 | **Multiple active tax configs** | Two `tax_configurations` rows both have `is_active = 1`. | DB: enforce only one active via application-level check + clear old active on new activation. |
| 14 | **Large report export performance** | Excel export for a full year of data may time out. | Limit default export range; note this as a future pagination/queue concern. |
| 15 | **billing_items double-invoicing** | A `billing_items` row with `status = 'draft'` included in two invoice generation calls. | On invoice creation, immediately set `billing_items.status = 'invoiced'` inside a transaction. |

---

## 12. Phased Implementation Roadmap

Based on PROTOTYPE_SCOPE.md. Each phase must be analyzed, implemented, tested, and
confirmed before the next phase begins.

---

### Phase 1 — Foundation

**Goal:** Running application skeleton with authentication and application shell.
**Dependencies:** None.

**Tasks:**
- Initialize CodeIgniter 4 project (`backend/`) with `.env`, CORS config, base JSON response format
- Initialize React + Vite project (`frontend/`) with folder structure as proposed
- Configure MySQL database connection
- Create migrations: `users`, `roles`, `menu_permissions`
- Seed: 2 roles (developer, admin), 1 developer account, 1 admin account, default menu permissions
- Implement `POST /api/v1/auth/login` → returns JWT
- Implement `POST /api/v1/auth/logout`
- Implement `GET /api/v1/auth/me` → returns user + role
- Implement `AuthFilter` (protect all `/api/v1/*` except login)
- Implement `RoleFilter` stub (ready for Phase 2+)
- Build React login page with form validation
- Build Zustand auth store (token, user, role)
- Build React Router with protected routes and role-based redirect
- Build application shell: Sidebar with role-filtered menu, Header with user name and logout
- Dashboard placeholder page

**Expected Result:** Developer logs in, sees full menu. Admin logs in, sees operational menu.
**Test Scope:** Login valid/invalid credentials. Route protection. Role menu visibility. Logout.

---

### Phase 2 — Master Data

**Goal:** All reference data required for billing is manageable through the UI.
**Dependencies:** Phase 1.

**Tasks:** Full CRUD + soft delete + search + filter for:
Company, Customer + PIC, Project, Cluster, Block, Lot,
IPL Rates, Water Rate Groups + Tiers, Tax Configuration, Signatures.

**Expected Result:** Admin can configure all master data without touching the database directly.
**Test Scope:** Create, read, update, soft-delete each entity. Search and filter. Validation errors.

---

### Phase 3 — Ownership

**Goal:** Link customers to properties with billing configuration.
**Dependencies:** Phase 2.

**Tasks:** Ownership CRUD, residential vs commercial property linking,
assign IPL rate and water rate group per ownership.

**Expected Result:** Each customer–property relationship has a complete billing profile.
**Test Scope:** Create residential and commercial ownerships. Validate required fields per type.

---

### Phase 4 — Meter Reading

**Goal:** Record and track water meter readings per ownership.
**Dependencies:** Phase 3.

**Tasks:** Meter reading input form, usage auto-calculation (current − previous),
photo upload, reading history view, previous reading auto-populated from last record.

**Expected Result:** Admin records readings; system displays m³ usage automatically.
**Test Scope:** Usage calculation. Reject current < previous. Photo upload. History list.

---

### Phase 5 — Billing Engine

**Goal:** Generate billing items from ownership configuration without manual calculation.
**Dependencies:** Phase 3, Phase 4.

**Tasks:**
- IPL: area × rate → billing_items
- Water: progressive tier calculation → billing_items + billing_item_tiers + abonemen
- Electricity: PLN charge + configurable management fee → billing_items
- Other charges: manual amount entry → billing_items
- Duplicate billing prevention per ownership per period

**Expected Result:** System generates correct billing amounts. Admin sees itemized breakdown.
**Test Scope:** IPL calculation. Water tier calculation with example from BUSINESS_RULES.md.
Electricity management fee. Duplicate billing rejection.

---

### Phase 6 — Tax Calculation

**Goal:** Backend TaxService applies current tax rules from DB config.
**Dependencies:** Phase 5.

**Tasks:** TaxService reading from `tax_configurations`, preview endpoint (`/billing/calculate-tax`),
integration into invoice generation flow.

**Expected Result:** DPP Nilai Lain and PPN 12% calculated correctly from DB config.
**Test Scope:** Verify with example from BUSINESS_RULES.md: Rp855,000 → Rp783,750 → Rp94,050 → Rp877,800.

---

### Phase 7 — Invoice

**Goal:** Generate, preview, and manage invoices from billing items.
**Dependencies:** Phase 5, Phase 6.

**Tasks:** Invoice generation (separate and combined), auto invoice number,
invoice_items snapshot, billing_items marked 'invoiced', status management,
invoice detail UI with line items, terbilang display.

**Expected Result:** Admin generates a correctly numbered invoice from billing items.
**Test Scope:** Separate invoice. Combined invoice. Auto number. Duplicate invoice prevention.
Status transitions. Terbilang text.

---

### Phase 8 — PDF + Mock WhatsApp

**Goal:** Generate downloadable PDF invoices and simulate WhatsApp delivery.
**Dependencies:** Phase 7.

**Tasks:** DomPDF integration, invoice PDF template (HTML/CSS),
WhatsAppService + MockWhatsAppProvider, WhatsApp send UI (button, phone preview,
message preview, confirmation modal, mock result), whatsapp_logs saved.

**Expected Result:** Admin downloads invoice PDF. Admin simulates WhatsApp send. Log recorded.
**Test Scope:** PDF content matches invoice data. Mock success path. Mock failure path. Log entries.

---

### Phase 9 — Payment Recording

**Goal:** Record external payments against invoices.
**Dependencies:** Phase 7.

**Tasks:** Payment form (date, amount, method, notes, optional proof upload),
payment list per invoice, PaymentService status recalculation,
invoice status transitions: unpaid → partially_paid → paid.

**Expected Result:** Admin records payment; invoice status updates correctly.
**Test Scope:** Full payment → paid. Partial payment → partially_paid. Second payment → paid.

---

### Phase 10 — Receipt

**Goal:** Generate receipt documents for recorded payments.
**Dependencies:** Phase 9.

**Tasks:** ReceiptService, auto receipt number, receipt PDF template (DomPDF),
receipt list page, PDF preview and download.

**Expected Result:** Admin generates and downloads a receipt after payment is recorded.
**Test Scope:** Receipt number generation. PDF content. Link to payment and invoice.

---

### Phase 11 — Reports

**Goal:** Operational reports with filtering and Excel export.
**Dependencies:** Phase 9, Phase 10.

**Tasks:** Periodic billing report (IPL / Water / Other), payment receipt report,
date/customer/project filters, Excel export via PhpSpreadsheet.

**Expected Result:** Admin can filter and export reports for any date range.
**Test Scope:** Filter by date. Filter by customer. Filter by project. Excel file downloads.

---

### Phase 12 — Dashboard

**Goal:** Real-time billing and payment summary visible on login.
**Dependencies:** Phase 11.

**Tasks:** Monthly/yearly received totals, outstanding aging buckets
(1–3 / 4–6 / 7–12 / >12 months), simple charts (Recharts),
recent invoices widget, recent payments widget.
All values from live DB queries — nothing hardcoded.

**Expected Result:** Dashboard reflects live transaction data immediately after any payment.
**Test Scope:** Totals match report data. Outstanding buckets correct. Charts render.

---

## 13. Recommended Phase 1

Phase 1 is the only blocker for every other phase. Nothing can be built without
working authentication, role enforcement, and the application shell.

---

### Backend — Phase 1 Scope

| Item | Detail |
|---|---|
| CI4 project setup | `.env`, CORS config, base response format (`{success, data, message, errors}`) |
| Migrations | `roles`, `users`, `menu_permissions` |
| Seeds | 2 roles, 1 developer account, 1 admin account, default menu permissions |
| `POST /api/v1/auth/login` | Validates credentials, returns JWT + user info |
| `POST /api/v1/auth/logout` | Invalidates/clears token |
| `GET /api/v1/auth/me` | Returns authenticated user + role + permissions |
| `AuthFilter` | Protects all `/api/v1/*` except login |
| `RoleFilter` stub | Ready to enforce permissions in Phase 2+ |

### Frontend — Phase 1 Scope

| Item | Detail |
|---|---|
| Vite + React setup | Folder structure as defined in Section 6 |
| `axiosClient.js` | Base URL from `.env`, JWT interceptor, 401 auto-logout |
| `authApi.js` | login(), logout(), me() functions |
| `authStore.js` (Zustand) | Stores token, user object, role |
| Login page | Form with email + password, validation, error display |
| `AuthGuard.jsx` | Redirects to login if no valid token |
| `RoleGuard.jsx` | Stub: blocks route by role |
| Application shell | Sidebar with role-filtered menu items, Header with user name + logout button |
| Dashboard placeholder | Empty page confirming successful authentication |

### What is NOT in Phase 1

- No business data (no company, customer, project, etc.)
- No billing, invoices, PDF, or WhatsApp
- No reports or dashboard data queries

### Definition of Done — Phase 1

1. Developer can log in with seeded credentials.
2. Developer sees the full sidebar menu.
3. Admin can log in with seeded credentials.
4. Admin sees the operational-only sidebar menu.
5. Protected routes redirect to login if not authenticated.
6. Logout clears token and redirects to login.
7. `GET /api/v1/auth/me` returns correct user and role data.

---

*End of ARCHITECTURE.md*

*This document is analysis only. No application code, migrations, or packages have been
created. Implementation begins only after this document is reviewed and approved.*
