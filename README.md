# IPU Billing & Invoice Management System

A modern, enterprise-grade web application for managing property utilities, progressive water billing, tax-compliant invoicing, payment processing, and electronic receipts. Built with **CodeIgniter 4 (PHP 8.2+)** REST API backend and **React 19 + Vite + TypeScript** frontend.

---

## Table of Contents

- [Overview & Key Features](#overview--key-features)
- [Architecture & Tech Stack](#architecture--tech-stack)
- [Core Business Logic & Tax Compliance](#core-business-logic--tax-compliance)
- [System Requirements & Prerequisites](#system-requirements--prerequisites)
- [Step-by-Step Installation Guide](#step-by-step-installation-guide)
  - [1. Database Setup](#1-database-setup)
  - [2. Backend Setup (CodeIgniter 4)](#2-backend-setup-codeigniter-4)
  - [3. Frontend Setup (React + Vite)](#3-frontend-setup-react--vite)
- [Default Login Credentials](#default-login-credentials)
- [API Reference](#api-reference)
- [Project Directory Structure](#project-directory-structure)

---

## Overview & Key Features

The **IPU Billing & Invoice Management System** streamlines property utility operations, automated calculation, and revenue management for residential clusters and commercial complexes.

### 🌟 Key Features

1. **Master Data & Property Hierarchy**:
   - Company profiles, customers (Individual, PT, CV, Institution), and multi-PIC contacts.
   - Multi-tier property structure: `Company` → `Project` → `Cluster` → `Block` → `Lot/Kavling`.

2. **Ownership & Utility Mapping**:
   - Map customer ownerships to specific residential lots or commercial areas.
   - Configurable IPL rates (per m²) and progressive water rate groups.

3. **Water Meter Reading with Photo Documentation**:
   - Record previous and current meter readings with automated volume calculation ($m^3$).
   - Upload and store photographic verification for audit compliance.

4. **Automated Billing Engine**:
   - **IPL Generation**: Area ($m^2$) $\times$ Rate/m² with date ranges.
   - **Progressive Water Billing**: Tiered rate calculations + fixed monthly abonemen charge.

5. **Tax-Compliant Invoicing (DPP Nilai Lain & PPN 12%)**:
   - Authoritative backend tax engine executing Indonesia's statutory *DPP Nilai Lain* formula.
   - Live tax preview before persistent invoice generation.
   - Flexible invoicing: Support for **Separated Invoices** (single billing item) and **Combined Invoices** (IPL + Water bundled per ownership).

6. **Automated PDF Generation**:
   - **Invoice PDF**: Corporate green template featuring 4-column layout, spelled-out amount (*Terbilang*), conditional water meter photo documentation, and dynamic digital signatures.
   - **Kwitansi (Payment Receipt) PDF**: Formal Indonesian receipt generated upon invoice payment.

7. **Dynamic Signature Management**:
   - Manage authorized signatories (e.g., *Pimpinan*, *Direktur*, *Finance Manager*) with uploaded signature images.
   - Embedded into PDFs via secure Base64 data encoding for high-fidelity rendering in Dompdf.

8. **Payment Recording Lifecycle**:
   - Record payments against invoices with methods (Transfer BCA, Mandiri, Cash, QRIS) and transaction references.
   - Real-time transition to **Paid (Lunas)** with automated receipt generation.

9. **WhatsApp Notification Simulator**:
   - Mock delivery pipeline simulating real-world automated WhatsApp dispatch with audit logs.

10. **Interactive Analytics & Reporting**:
    - **Dashboard Analytics**: Revenue trends chart (*Recharts*) comparing Paid vs. Outstanding monthly totals.
    - **Excel Export**: Generate formatted tabular `.xlsx` spreadsheets (*PhpSpreadsheet*) with corporate styling and formula-driven summaries.

---

## Architecture & Tech Stack

```
┌────────────────────────────────────────────────────────┐
│                   React 19 Frontend                    │
│      Vite · TypeScript · TanStack Query · Recharts     │
└───────────────────────────┬────────────────────────────┘
                            │ HTTP / REST (JWT Auth)
┌───────────────────────────▼────────────────────────────┐
│                CodeIgniter 4 REST API                  │
│       PHP 8.2+ · Dompdf · PhpSpreadsheet · MVC         │
└───────────────────────────┬────────────────────────────┘
                            │ MySQLi
┌───────────────────────────▼────────────────────────────┐
│                    MySQL Database                      │
│                  InnoDB · UTF8mb4                      │
└────────────────────────────────────────────────────────┘
```

### Backend
- **Framework**: [CodeIgniter 4.7+](https://codeigniter.com/)
- **PHP Version**: PHP 8.2 / 8.3
- **Database**: MySQL 8.0+ / MariaDB (Laragon compatible)
- **PDF Engine**: [Dompdf 3.1](https://github.com/dompdf/dompdf)
- **Spreadsheet Engine**: [PhpSpreadsheet 5.9](https://github.com/PHPOffice/PhpSpreadsheet)
- **Authentication**: JWT via `firebase/php-jwt 7.1`

### Frontend
- **Framework**: [React 19](https://react.dev/) + [Vite 8](https://vitejs.dev/)
- **Language**: TypeScript 6
- **State Management**: [Zustand 5](https://github.com/pmndrs/zustand)
- **Data Fetching**: [TanStack Query v5](https://tanstack.com/query/latest)
- **Data Visualization**: [Recharts 2.x](https://recharts.org/)
- **HTTP Client**: [Axios](https://axios-http.com/) with automated Bearer interceptors
- **Icons**: [Lucide React](https://lucide.dev/)

---

## Core Business Logic & Tax Compliance

The backend acts as the single source of truth for all financial and mathematical computations.

### 1. Indonesian DPP Nilai Lain Tax Formula (PPN 12%)
Pursuant to Indonesian tax regulations for property & utility management:

$$\text{DPP Nilai Lain} = \frac{11}{12} \times \text{Subtotal DPP}$$

$$\text{PPN Amount} = 12\% \times \text{DPP Nilai Lain}$$

$$\text{Grand Total} = \text{Subtotal DPP} + \text{PPN Amount}$$

*Example calculation for Subtotal DPP of Rp 752.500:*
- $\text{DPP Nilai Lain} = \frac{11}{12} \times 752.500 = \text{Rp } 690.625$
- $\text{PPN (12\%)} = 0{,}12 \times 690.625 = \text{Rp } 82.875$
- $\text{Grand Total} = 752.500 + 82.875 = \text{Rp } 835.375$

### 2. Progressive Water Billing Engine
Water billing uses tiered block tariffs plus a fixed base rate (*Abonemen*):

$$\text{Total Water Cost} = \text{Abonemen} + \sum_{i} (\text{Usage in Tier}_i \times \text{Tariff}_i)$$

- **Tier 1 (0 – 20 $m^3$)**: Rp 7.500 / $m^3$
- **Tier 2 (20 – 40 $m^3$)**: Rp 8.500 / $m^3$
- **Tier 3 (> 40 $m^3$)**: Rp 9.500 / $m^3$
- **Base Abonemen**: Rp 45.000

---

## System Requirements & Prerequisites

Ensure the following tools are installed on your system:

| Prerequisite | Minimum Version | Recommended / Tested |
|---|---|---|
| **PHP** | `^8.2` | PHP 8.2.x or PHP 8.3.x with `intl`, `mbstring`, `gd`, `mysqli`, `curl` |
| **Composer** | `^2.2` | Composer 2.7+ |
| **Node.js** | `^18.0` | Node.js 20.x or 22.x LTS |
| **npm** | `^9.0` | npm 10.x |
| **Database** | MySQL 8.0+ / MariaDB 10.4+ | Laragon MySQL (`127.0.0.1:3306`) |

---

## Step-by-Step Installation Guide

### 1. Database Setup

1. Start your MySQL service (e.g., via **Laragon** or **XAMPP**).
2. Create a new database named `ipu_billing`:

```sql
CREATE DATABASE IF NOT EXISTS ipu_billing CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

---

### 2. Backend Setup (CodeIgniter 4)

1. Open your terminal and navigate to the `backend/` directory:

```bash
cd backend
```

2. Install PHP dependencies:

```bash
composer install
```

3. Configure environment settings:
   Ensure `backend/.env` exists and contains your database credentials:

```ini
CI_ENVIRONMENT = development

app.baseURL = 'http://localhost:8080/'

database.default.hostname = 127.0.0.1
database.default.database = ipu_billing
database.default.username = root
database.default.password = 
database.default.DBDriver = MySQLi
database.default.port     = 3306

JWT_SECRET = 'your-secure-jwt-secret-key-change-in-production'
JWT_EXPIRE_HOURS = 24
```

4. Run database migrations to construct the schema:

```bash
php spark migrate
```

5. Seed initial master data, roles, users, and demo units:

```bash
php spark db:seed MainSeeder
```

6. Start the CodeIgniter 4 backend server:

```bash
php spark serve --port 8080
```

> 🌐 Backend API will be running at: **`http://localhost:8080`**

---

### 3. Frontend Setup (React + Vite)

1. Open a new terminal window and navigate to the `frontend/` directory:

```bash
cd frontend
```

2. Install Node.js dependencies:

```bash
npm install
```

3. Start the Vite development server:

```bash
npm run dev
```

> 💻 Frontend Application will be accessible at: **`http://localhost:5173`**

---

## Default Login Credentials

Use the pre-seeded account to evaluate the application:

| Attribute | Value |
|---|---|
| **URL** | `http://localhost:5173/login` |
| **Email** | `dev@ipu-billing.local` |
| **Password** | `dev_password_change_me` |
| **Role** | `developer` (Full System Administrator) |

---

## API Reference

Base API URL: `http://localhost:8080/api/v1`

### Authentication
- `POST /auth/login` — Authenticate and receive JWT Bearer token.
- `GET  /auth/me` — Retrieve active user session details.
- `POST /auth/logout` — Revoke user session.

### Dashboard & Analytics
- `GET  /dashboard/summary` — Operational metrics and 6-month revenue trends.
- `GET  /reports/export-invoices` — Stream formatted `.xlsx` invoice recap report.

### Invoices & Tax
- `GET  /invoices` — List all generated invoices with status filters.
- `GET  /invoices/{id}` — Invoice details with items and relations.
- `POST /invoices/preview-tax` — Authoritative tax preview calculation.
- `POST /invoices/generate` — Generate invoice from selected draft billing items.
- `GET  /invoices/{id}/pdf` — Stream generated Invoice PDF document.
- `GET  /invoices/{id}/receipt` — Stream generated Kwitansi (Receipt) PDF document.
- `POST /invoices/{id}/send-whatsapp` — Simulate WhatsApp notification delivery.

### Payments
- `POST /payments` — Record payment transaction and mark invoice as `paid`.
- `GET  /payments/invoice/{invoiceId}` — Retrieve payment record for invoice.

### Property & Master Data
- `GET/POST/PUT/DELETE /customers` — Customer management.
- `GET/POST/PUT/DELETE /ownerships` — Property ownership records.
- `GET/POST/PUT/DELETE /meter-readings` — Water meter recording & photo uploads.
- `GET/POST/PUT/DELETE /signatures` — Authorized signatories with image upload.
- `POST /billing/generate-ipl` — Batch IPL billing items generator.
- `POST /billing/generate-water` — Batch water billing items generator.

---

## Project Directory Structure

```
IPU-Billing-System/
├── backend/                         # CodeIgniter 4 REST API
│   ├── app/
│   │   ├── Config/Routes.php        # API endpoint definitions
│   │   ├── Controllers/Api/         # REST API Controllers (Invoice, Payment, Dashboard, Report, etc.)
│   │   ├── Database/
│   │   │   ├── Migrations/          # Database migrations
│   │   │   └── Seeds/               # Data Seeders (MainSeeder, Phase2Seeder, Roles, etc.)
│   │   ├── Models/                  # CodeIgniter Data Models
│   │   ├── Services/                # Business logic engines (InvoiceService, DashboardService, etc.)
│   │   └── Views/invoices/          # Dompdf HTML Templates (pdf_template, kwitansi_template)
│   ├── writable/uploads/            # Uploaded photos & signatures
│   └── composer.json
│
├── frontend/                        # React + Vite + TypeScript
│   ├── src/
│   │   ├── api/                     # Axios API service clients (invoiceApi, paymentApi, reportApi, etc.)
│   │   ├── components/ui/           # Reusable UI component library (Card, Modal, Badge, Button, etc.)
│   │   ├── pages/                   # Application Pages
│   │   │   ├── DashboardPage.tsx    # Analytics dashboard with Recharts & KPI cards
│   │   │   ├── invoice/             # Invoice generator, tax preview, PDF & payment modal
│   │   │   ├── billing/             # Billing item generator
│   │   │   ├── master/              # Master data management (Customers, Signatures, Rates)
│   │   │   └── ownership/           # Property unit ownership management
│   │   ├── stores/                  # Zustand state stores (authStore)
│   │   └── App.tsx                  # Routing & layout setup
│   ├── package.json
│   └── vite.config.ts
│
├── docs/                            # Architectural documentation & technical specs
└── README.md                        # Master project documentation
```

---

## License & Attribution

Developed for **IPU Billing & Property Management System**.  
All rights reserved © 2026.
