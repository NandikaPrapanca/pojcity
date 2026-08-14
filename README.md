# IPU Billing & Invoice Management System

Web-based internal administrative system for managing property billing, invoices, payments, and receipts.

**Phase 1** — Authentication & Role Management (complete)

---

## Prerequisites

| Tool | Version | Location |
|---|---|---|
| PHP | 8.3.x | `C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe` |
| Composer | 2.9.x | `C:\laragon\bin\composer\composer.phar` |
| MySQL | 8.4.x | Laragon — `127.0.0.1:3306` |
| Node.js | 22.x | System PATH |
| npm | 10.x | System PATH |

> Laragon must be running for MySQL to be available.

---

## Project Structure

```
IPU-Billing-System/
├── backend/          ← CodeIgniter 4 REST API
├── frontend/         ← React + Vite + TypeScript
├── docs/             ← Architecture, business rules, project spec
└── README.md
```

---

## Backend Setup

### 1. Configure Environment

```bash
# backend/.env is already configured for local dev
# Review and update if needed:
# - database credentials
# - JWT_SECRET (change in production)
```

### 2. Create Database

Using MySQL/Laragon (run once):

```sql
CREATE DATABASE IF NOT EXISTS ipu_billing CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

Or with CLI:
```bash
C:\laragon\bin\mysql\mysql-8.4.3-winx64\bin\mysql.exe -u root -e "CREATE DATABASE IF NOT EXISTS ipu_billing CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
```

### 3. Run Migrations

```bash
cd backend
C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe spark migrate
```

### 4. Run Seeds

```bash
C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe spark db:seed MainSeeder
```

This seeds:
- 2 roles: `developer`, `admin`
- 1 developer account: `dev@ipu-billing.local` / `dev_password_change_me`

### 5. Start Backend Server

```bash
C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe spark serve --port 8080
```

Backend runs at: **http://localhost:8080**

---

## Frontend Setup

### 1. Install Dependencies (first time)

```bash
cd frontend
npm install
```

### 2. Start Frontend Dev Server

```bash
cd frontend
npm run dev
```

Frontend runs at: **http://localhost:5173**

---

## Running Both (Phase 1 Development)

Open two terminal windows:

**Terminal 1 — Backend:**
```bash
cd D:\Nan\IPU-Billing-System\backend
C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe spark serve --port 8080
```

**Terminal 2 — Frontend:**
```bash
cd D:\Nan\IPU-Billing-System\frontend
npm run dev
```

Then open **http://localhost:5173** in your browser.

---

## Phase 1 API Endpoints

Base URL: `http://localhost:8080/api/v1`

| Method | Endpoint | Auth | Description |
|---|---|---|---|
| POST | `/auth/login` | No | Login — returns JWT token |
| GET | `/auth/me` | Bearer token | Get authenticated user info |
| POST | `/auth/logout` | Bearer token | Logout (client clears token) |

### Login Request

```json
POST /api/v1/auth/login
Content-Type: application/json

{
  "email": "dev@ipu-billing.local",
  "password": "dev_password_change_me"
}
```

### Login Response

```json
{
  "success": true,
  "data": {
    "token": "<jwt>",
    "user": {
      "id": "1",
      "name": "Developer",
      "email": "dev@ipu-billing.local",
      "role": "developer"
    }
  },
  "message": "Login berhasil.",
  "errors": null
}
```

---

## Development Credentials

| Field | Value |
|---|---|
| Email | `dev@ipu-billing.local` |
| Password | `dev_password_change_me` |
| Role | `developer` |

> Change `DEV_SEED_PASSWORD` in `backend/.env` before seeding in a different environment.

---

## Phase 1 Tech Stack

**Backend:**
- CodeIgniter 4.7.4
- PHP 8.3
- firebase/php-jwt 7.1
- MySQL 8.4

**Frontend:**
- React 19 + Vite 8
- TypeScript 6
- React Router 7
- Axios 1.x
- Zustand 5 (auth state)
- TanStack Query 5
- React Hook Form 7 + Zod 4

---

## Roadmap

- **Phase 1** ✅ Authentication, roles, JWT, protected routes
- **Phase 2** — Master data: Company, Customer, PIC, Project, Cluster, Block, Lot
- **Phase 3** — Ownership, Meter Reading
- **Phase 4** — Billing engine (IPL, Water, Electricity, Other)
- **Phase 5** — Invoice generation, Tax calculation
- **Phase 6** — PDF generation, Mock WhatsApp
- **Phase 7** — Payment recording, Receipts
- **Phase 8** — Reports, Dashboard refinement
