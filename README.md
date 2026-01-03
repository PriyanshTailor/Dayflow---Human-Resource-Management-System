# 🌟 Dayflow HRMS

<p align="center">
    <img src="frontend/assets/readme/logo.png" alt="Dayflow HRMS Logo" width="220" />
</p>

> A clean, role-aware Human Resource Management System for attendance, leave, payroll, and profiles — built with PHP, MySQL, and vanilla HTML/CSS/JS on XAMPP.

[![PHP](https://img.shields.io/badge/PHP-8+-8892BF?logo=php&logoColor=white)](https://www.php.net/)
[![MySQL](https://img.shields.io/badge/MySQL-8+-4479A1?logo=mysql&logoColor=white)](https://www.mysql.com/)
[![Status](https://img.shields.io/badge/Status-Active-success)](#)
[![License](https://img.shields.io/badge/License-MIT-blue.svg)](#)
[![PRs](https://img.shields.io/badge/PRs-welcome-brightgreen)](#)

<details>
<summary><strong>Table of Contents</strong></summary>

- [Overview](#overview)
- [Feature Snapshot](#feature-snapshot)
- [Architecture at a Glance](#architecture-at-a-glance)
- [Visual Gallery (placeholders)](#visual-gallery-placeholders)
- [Directory Map](#directory-map)
- [Setup & Run](#setup--run)
- [Environment](#environment)
- [Database Schema](#database-schema)
- [Workflows](#workflows)
- [Request Lifecycle](#request-lifecycle)
- [Endpoints (high level)](#endpoints-high-level)
- [Frontend Pages](#frontend-pages)
- [Security & Ops Notes](#security--ops-notes)
- [Development Tips](#development-tips)
- [Roadmap Ideas](#roadmap-ideas)
</details>

## Overview
Dayflow HRMS delivers two tailored experiences: **admins** manage people, attendance approvals, and payroll; **employees** track attendance, request leave, and view salary details. Everything is lightweight, self-hostable, and friendly to local stacks (XAMPP/LAMP/WAMP).

## Feature Snapshot
- 🗂️ Clear separation of **frontend** (static HTML/CSS/JS) and **backend** (PHP APIs with PDO)
- 👤 Role-aware dashboards: admin vs employee
- 🔐 Bcrypt passwords, session-based auth, optional SMTP notifications with file-log fallback
- 🗃️ Core modules: attendance, leave, salary, payroll history, notifications
- 🧭 Simple fetch-based frontends that talk to PHP JSON endpoints

## Architecture at a Glance
```mermaid
flowchart LR
    Frontend[HTML/CSS/JS] -- fetch --> API[PHP endpoints]
    API -- PDO --> DB[(MySQL / MariaDB)]
    API -- optional SMTP --> Mail[SMTP Server]
    API -- fallback log --> Logs[/backend/logs/email_fallback.log/]
```

## Visual Gallery (placeholders)
> Drop your own images into `frontend/assets/readme/` (create if missing). Use the filenames below so the README renders automatically:
> - `logo.png` (used in the header)
> - `login.png`
> - `admin-dashboard.png`
> - `employee-dashboard.png`
> - `attendance.png`
> - `leave.png`
> - `payroll.png`

| Screen | Description | Path |
| --- | --- | --- |
| ![Login](frontend/assets/readme/login.png) | Public login view | `frontend/assets/readme/login.png` |
| ![Admin Dashboard](frontend/assets/readme/admin-dashboard.png) | Admin KPIs & quick links | `frontend/assets/readme/admin-dashboard.png` |
| ![Employee Dashboard](frontend/assets/readme/employee-dashboard.png) | Employee snapshot: attendance, leave, payroll | `frontend/assets/readme/employee-dashboard.png` |
| ![Attendance](frontend/assets/readme/attendance.png) | Attendance tracking/approvals | `frontend/assets/readme/attendance.png` |
| ![Leave](frontend/assets/readme/leave.png) | Leave request & approvals | `frontend/assets/readme/leave.png` |
| ![Payroll](frontend/assets/readme/payroll.png) | Payroll history/generation | `frontend/assets/readme/payroll.png` |

## Directory Map
```
backend/
  admin/                  # Admin endpoints (employees, attendance approvals, payroll)
  auth/                   # Login / logout
  employee/               # Employee endpoints (attendance, leave, payroll, profile)
  config/                 # Database, email, session helpers
  upload-profile-picture.php
frontend/
  admin/                  # Admin dashboards
  auth/                   # Public login page
  employee/               # Employee dashboards
  assets/                 # Shared JS / CSS
hrms.sql                  # Full DB schema + seed admin user
fix_password.sql          # Helper to reset a specific user password
.env                      # SMTP settings (sample values)
```

## Setup & Run
1) Install **PHP 8+**, **MySQL/MariaDB**, and a web server (XAMPP/LAMP/WAMP).
2) Create database `hrms` and import `hrms.sql`.
3) (Optional) Reset a specific user password with `fix_password.sql`.
4) Update DB credentials in `backend/config/database.php` to match your local DB.
5) Configure SMTP in `.env` (or rely on fallback logs at `backend/logs/email_fallback.log`).
6) Place the project under your web root so routes resolve like `/hrms/frontend/...` and `/hrms/backend/...`.
7) Login via `frontend/auth/login.html` using the seed admin: `admin@hrms.com` / `password` (from `hrms.sql`).

## Environment
Defined in `.env` (already present):

| Key | Description |
| --- | --- |
| `SMTP_HOST`, `SMTP_PORT` | SMTP host/port |
| `SMTP_USERNAME`, `SMTP_PASSWORD` | SMTP credentials |
| `SMTP_ENCRYPTION` | `tls` / `ssl` / empty |
| `SMTP_FROM_EMAIL`, `SMTP_FROM_NAME` | From identity for outbound mail |

> Database credentials are currently in `backend/config/database.php`. You can align with `.env` for consistency if desired.

## Database Schema
**Tables**

| Table | Purpose |
| --- | --- |
| `users` | Employee/admin master data, auth (bcrypt passwords), role, profile info |
| `attendance` | Daily check-in/out, working hours, status, approval metadata |
| `leave_requests` | Leave applications with type, dates, status, approver comments |
| `salary` | Current salary components with generated `net_salary` column |
| `payroll_history` | Monthly payroll snapshots with attendance-derived deductions |
| `notifications` | User notifications (message, type, related_id, read flag) |

**ERD**
```mermaid
erDiagram
    users ||--o{ attendance : "records"
    users ||--o{ leave_requests : "requests"
    users ||--o{ salary : "current"
    users ||--o{ payroll_history : "history"
    users ||--o{ notifications : "receives"

    users {
        int user_id PK
        string employee_id
        string name
        string email
        string password
        enum role
    }

    attendance {
        int attendance_id PK
        int user_id FK
        date date
        time check_in_time
        time check_out_time
        decimal working_hours
        enum status
        enum approval_status
        int approved_by
        text remarks
    }

    leave_requests {
        int leave_id PK
        int user_id FK
        enum leave_type
        date start_date
        date end_date
        text reason
        enum status
        text admin_comment
        int approved_by
    }

    salary {
        int salary_id PK
        int user_id FK
        decimal basic_salary
        decimal allowances
        decimal deductions
        decimal net_salary
        string currency
        date effective_date
    }

    payroll_history {
        int payroll_id PK
        int user_id FK
        string month
        decimal gross_salary
        decimal allowances
        decimal base_deductions
        decimal attendance_deductions
        decimal net_payable
        decimal present_days
        decimal half_days
        decimal leave_days
        decimal absent_days
        decimal payable_days
    }

    notifications {
        int notification_id PK
        int user_id FK
        text message
        string type
        int related_id
        bool is_read
    }
```

## Workflows

### Authentication & Session Flow
```mermaid
sequenceDiagram
    autonumber
    participant User
    participant Frontend as Frontend (login.html)
    participant Backend as backend/auth/login.php
    participant DB as MySQL

    User->>Frontend: Submit login (email or employee_id + password)
    Frontend->>Backend: POST JSON {login, password}
    Backend->>DB: Validate user & bcrypt password
    DB-->>Backend: User row
    Backend-->>Frontend: {success, role, session cookie}
    Frontend-->>User: Redirect by role (admin/employee)
```

### Admin Core Flow (hire → track → pay)


### Data Access Flow
```mermaid
flowchart LR
    Frontend[HTML/JS] -- fetch --> PHP[backend endpoints]
    PHP -- PDO --> DB[(MySQL)]
    PHP -- optional SMTP --> Email[SMTP server]
    PHP -- log fallback --> Files[logs/email_fallback.log]
```

## Request Lifecycle
```mermaid
sequenceDiagram
    autonumber
    participant FE as Frontend (JS)
    participant BE as PHP Endpoint
    participant DB as MySQL
    participant MAIL as SMTP/Log

    FE->>BE: fetch /backend/... (JSON)
    BE->>DB: Query / mutate via PDO
    DB-->>BE: Rows / status
    BE-->>FE: JSON {success, data, message}
    alt sends notification
        BE-->>MAIL: SMTP send or log fallback
    end
```

## Endpoints (high level)
- `backend/auth/login.php` – session login (email or employee_id + password)
- `backend/auth/logout.php` – destroy session
- `backend/admin/*` – add/update employees, attendance approvals, payroll generation
- `backend/employee/*` – attendance marking, leave requests, profile update, salary view
- `backend/upload-profile-picture.php` – profile photo uploads

## Frontend Pages
- `frontend/auth/login.html` – public login
- `frontend/admin/` – admin dashboard, employee CRUD, attendance, leave approvals, payroll, profile
- `frontend/employee/` – employee dashboard, apply leave, attendance view, salary, profile
- `frontend/assets/main.js` – login form validation + auth request

## Security & Ops Notes
- Bcrypt-hashed passwords; sessions for auth (harden cookies/HTTPS in production).
- If exposing publicly, cap upload size/type in `upload-profile-picture.php` and add CSRF tokens.
- Enable HTTPS + secure/session cookie flags in production.

## Development Tips
- Enable PHP error reporting in local dev as needed.
- Keep `.env` out of version control.
- Consider moving DB credentials into `.env` and loading them in `database.php` for parity with SMTP config.

## Roadmap Ideas
- CSRF tokens on mutating routes
- Pagination/filtering on admin listings
- Audit logs for approvals and payroll runs
- Small router to centralize auth checks and JSON responses

---
Feel free to rebrand the visuals and wording to match your organization. Happy shipping! 🚀
