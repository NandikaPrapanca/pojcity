# IPU Billing & Invoice Management System

## 1. Project Overview

IPU Billing & Invoice Management System is a web-based internal administrative application designed to replace manual Excel-based workflows used for managing customer data, property data, billing, invoices, payments, receipts, and reports.

The main objective is:

> Minimize manual data entry, manual calculation, and repetitive administrative work.

The application is primarily used by administrative staff and system developers.

This project is currently a PROTOTYPE / MVP.

There is no existing source code. The system is built from scratch.

---

## 2. Technology Stack

Backend:
- CodeIgniter 4
- PHP
- REST API

Frontend:
- React
- Vite
- React Router

Database:
- MySQL

Architecture:

React Frontend
      ↓
REST API
      ↓
CodeIgniter Backend
      ↓
MySQL Database

---

## 3. User Roles

### Developer

Developer is responsible for system administration.

Access:
- Dashboard
- All master data
- All transactions
- Invoice
- Payment
- Reports
- User accounts
- Menu access
- System settings

Developer can:
- Create admin accounts
- Edit admin accounts
- Disable admin accounts
- Configure menu access

### Admin

Admin handles daily operational activities.

Access:
- Dashboard
- Company
- Customer
- Project
- Ownership
- Meter
- IPL
- Water
- Electricity
- Other charges
- Invoice
- Payment recording
- Receipt
- Reports

Admin cannot manage developer/system access unless explicitly permitted.

---

## 4. Core Business Purpose

The system should simplify this manual process:

Excel / Manual Data
→ Manual Calculation
→ Manual Invoice
→ Manual PDF
→ Manual WhatsApp
→ Manual Payment Recording
→ Manual Reporting

Into:

Web Application
→ Stored Master Data
→ Automatic Calculation
→ Invoice
→ PDF
→ WhatsApp Delivery
→ Payment Recording
→ Reports

The system should reuse existing data wherever possible.

---

## 5. Main Modules

### Dashboard
Global billing and payment summary.

### Master Data
- Perusahaan
- Customer
- PIC
- Project
- Cluster
- Block
- Lot / Kavling
- IPL pricing
- Water pricing
- Tax configuration
- Signature

### Transactions
- Ownership
- Meter Reading
- IPL
- Water
- Electricity
- Other Charges

### Billing
- Generate billing
- Invoice
- Combined invoice
- Separate invoice
- Payment recording
- Receipt

### Reports
- Periodic billing report
- Payment receipt report
- Excel export

### Settings
- User account
- Role
- Menu access
- Signature

---

## 6. Payment

There is NO payment gateway.

Customers do not pay inside this application.

Payment happens externally.

The application only allows administrators to record payments that have already been received.

Example:

Customer pays externally
→ Admin records payment
→ Invoice status becomes Paid
→ Receipt can be generated

Do not implement:
- Checkout
- E-wallet
- Virtual account
- Payment gateway
- Online payment processing

---

## 7. WhatsApp

WhatsApp delivery is part of the application's future automation.

For the prototype:

- Use a MOCK WhatsApp provider.
- Do not require Fonnte API credentials.
- Do not directly integrate Fonnte yet.

The architecture must allow a future Fonnte provider to replace the mock provider without changing the invoice workflow.

Expected flow:

Invoice
→ Generate PDF
→ Send WhatsApp
→ Mock success/failure
→ Save sending history

Future:

Invoice
→ Generate PDF
→ Fonnte
→ WhatsApp Customer

---

## 8. UI / UX Direction

The application is designed for non-technical administrative users.

Design principles:
- Simple
- Elegant
- Professional
- Calm
- Clear
- Easy to learn
- Minimal manual input

Visual direction:
- Corporate
- Clean
- Modern
- Inspired by the general visual character of IPU Land / Poj City Semarang

Do not directly copy proprietary logos or graphics.

Color direction:
- Muted deep green
- Sage / muted green
- Warm off-white
- White
- Dark charcoal
- Soft gray

Avoid:
- Neon colors
- Excessive gradients
- Excessive shadows
- Excessive glassmorphism
- Flashy SaaS styling

UI language:
Indonesian.

Use familiar labels such as:
- Tambah
- Simpan
- Ubah
- Hapus
- Cari
- Filter
- Generate
- Preview
- Download PDF
- Kirim WhatsApp

---

## 9. Development Principles

1. Build incrementally.
2. Do not implement the entire application in one step.
3. Analyze requirements before coding.
4. Propose the database architecture before implementation.
5. Keep business logic in the backend.
6. Do not duplicate business calculations in React.
7. Reuse master data.
8. Minimize manual input.
9. Use validation on frontend and backend.
10. Use proper decimal types for monetary values.
11. Preserve historical billing information.
12. Use soft delete for appropriate master/business data.
13. Do not physically delete important invoice/payment records.
14. Do not invent unclear business rules.
15. Mark unclear requirements as TODO / NEEDS CONFIRMATION.
16. Keep the prototype maintainable and easy to extend.

---

## 10. Reference Documents

Documents in `docs/reference/` are references for understanding the real business workflow and document format.

They are NOT automatically treated as system rules.

Historical data may contain older business practices or tax values.

Current prototype requirements in `BUSINESS_RULES.md` take priority over historical examples.

---

## 11. Development Strategy

The project should be developed in phases.

Recommended order:

1. Architecture and ERD
2. Project setup
3. Authentication and roles
4. Master data
5. Ownership
6. Meter
7. Billing engine
8. Invoice
9. PDF
10. Mock WhatsApp
11. Payment recording
12. Receipt
13. Reports
14. Dashboard refinement
15. Fonnte integration later

Never skip directly to advanced integrations before the core billing process works.

---

## 12. Definition of Success

The prototype is successful when an administrator can:

1. Select an existing customer.
2. Select their property/ownership.
3. Input meter information where required.
4. Generate billing automatically.
5. Calculate tax automatically.
6. Generate an invoice.
7. Preview/download the invoice as PDF.
8. Simulate sending the PDF through WhatsApp.
9. Record payment.
10. Generate a receipt.
11. View the result in reports/dashboard.

The system should require significantly less manual calculation and repetitive input than the original Excel-based workflow.