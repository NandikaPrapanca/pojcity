# Prototype Scope

## 1. Purpose

This document defines what is included and excluded from the first prototype.

The goal is to produce a working internal administrative billing prototype that demonstrates the core workflow from master data to invoice, PDF, WhatsApp simulation, payment recording, and reporting.

---

# 2. Prototype Users

Only two roles:

### Developer
System-level access.

### Admin
Operational access.

No additional roles are required for the first prototype.

---

# 3. INCLUDED IN PROTOTYPE

## Phase 1 — Foundation

- Project setup
- CodeIgniter 4 backend
- React frontend
- Vite
- MySQL
- REST API
- Login
- Authentication
- Basic role handling
- Basic application layout
- Sidebar
- Header

---

## Phase 2 — Master Data

### Company
- Create
- Read
- Update
- Soft delete

### Customer
- Create
- Read
- Update
- Search
- Filter
- Soft delete
- WhatsApp number
- PIC

### Project
- Commercial
- Residential

### Residential hierarchy

Project
→ Cluster
→ Block
→ Lot/Kavling

### Pricing
- IPL pricing
- Water pricing
- Water tier configuration

### Signature
- Admin signature
- Leadership signature

---

## Phase 3 — Ownership

Implement:

### Commercial
Customer
→ Project
→ Billing Address
→ Area
→ IPL Rate

### Residential
Customer
→ Project
→ Cluster
→ Block
→ Lot
→ Billing Address

Ownership should be reusable by billing processes.

---

## Phase 4 — Meter

Implement:

- Meter reading input
- Previous reading
- Current reading
- Automatic usage calculation
- Reading date
- Meter photo upload
- Reading history

Water:

Usage = Current - Previous

---

## Phase 5 — Billing

Implement:

### IPL
Area × IPL Rate

### Water
Progressive tier calculation
+
Abonemen where applicable

### Electricity
- Pasang Baru
- Tambah Daya
- Perbaikan
- PLN charge
- Configurable management fee

### Other
- Customer
- Charge name
- Amount
- Tax
- Description

---

## Phase 6 — Tax

Implement the current prototype calculation:

DPP Nilai Lain:
11/12 × Subtotal DPP

PPN:
12% × DPP Nilai Lain

Total:
DPP Nilai Lain + PPN

Calculation must happen in backend business logic.

---

## Phase 7 — Invoice

Implement:

- Invoice generation
- Auto invoice number
- Invoice preview
- Separate invoice
- Combined invoice
- IPL + Air combined billing
- Invoice status
- PDF generation
- Terbilang
- Signature

---

## Phase 8 — WhatsApp Mock

Implement only a mock provider.

Features:

- Send WhatsApp button
- Customer number
- Message preview
- PDF attachment preview
- Confirmation modal
- Mock success
- Mock failure
- Sending history
- Retry button

Do NOT integrate Fonnte in the first prototype.

---

## Phase 9 — Payment Recording

Implement:

- Select invoice
- Payment date
- Payment amount
- Payment method
- Notes
- Optional payment proof
- Payment status

No online payment processing.

---

## Phase 10 — Receipt

Implement:

- Generate receipt
- Receipt number
- Customer
- Amount
- Terbilang
- Payment description
- Payment date
- Signature
- PDF preview
- Download

---

## Phase 11 — Reports

Implement:

### Periodic Report
- IPL
- Air
- Other charges

### Payment Receipt Report

Include:
- Date filtering
- Customer filtering
- Project filtering where appropriate
- Excel export

---

## Phase 12 — Dashboard

Implement:

- Total received this month
- Total received this year
- Outstanding 1–3 months
- Outstanding 4–6 months
- Outstanding 7–12 months
- Outstanding >12 months
- Simple charts
- Recent invoices
- Recent payments

Dashboard values must be calculated from database data.

---

# 4. EXCLUDED FROM FIRST PROTOTYPE

Do NOT implement these yet:

- Real Fonnte API
- WhatsApp Business API production setup
- Payment gateway
- Online customer payment
- Customer portal
- Customer mobile application
- E-wallet
- Virtual account
- E-Faktur integration
- Automatic tax filing
- OCR meter reading
- AI invoice processing
- Email automation
- SMS automation
- Complex accounting integration
- ERP integration
- Multi-company accounting
- Advanced approval workflow
- Production deployment
- Kubernetes
- Complex DevOps infrastructure

These may be considered later.

---

# 5. Prototype Definition of Done

The prototype is considered functionally successful when this flow works:

1. Developer logs in.
2. Developer creates an Admin account.
3. Admin logs in.
4. Admin creates/loads company data.
5. Admin creates customer.
6. Admin creates project/property.
7. Admin creates ownership.
8. Admin records meter reading.
9. System calculates water usage.
10. Admin generates IPL/Water billing.
11. System calculates subtotal.
12. System calculates DPP Nilai Lain.
13. System calculates PPN 12%.
14. System calculates total.
15. Admin generates invoice.
16. Admin previews invoice.
17. Admin generates/downloads PDF.
18. Admin uses Mock WhatsApp.
19. System stores WhatsApp log.
20. Admin records payment.
21. Invoice becomes Paid.
22. Admin generates receipt.
23. Admin views reports.
24. Dashboard reflects the transaction data.

---

# 6. Development Rules

Build only one phase at a time.

For every phase:

1. Analyze.
2. Implement.
3. Test.
4. Fix.
5. Confirm completion.
6. Move to next phase.

Do not implement future phases without approval.

Do not create features merely because they appear useful.

Follow PROJECT.md and BUSINESS_RULES.md as the main project references.

When conflict exists:

1. Current confirmed project requirement
2. BUSINESS_RULES.md
3. PROTOTYPE_SCOPE.md
4. Historical reference documents
5. Developer assumptions

Never silently invent a missing requirement.

---

# 7. Prototype Philosophy

The prototype should demonstrate business value, not production complexity.

The primary question is:

"Does this system significantly reduce the amount of manual work previously performed in Excel?"

Every feature should support that goal.