# Drug Checklist Healthcare System

A professional healthcare web application for managing drug inventory, monitoring stock levels, tracking expiry dates, maintaining drug checklists, and generating reports. Built with a custom PHP backend and MySQL database — no frameworks, no external dependencies.

---

## Table of Contents

- [Technology Stack](#technology-stack)
- [Architecture Overview](#architecture-overview)
- [Database Schema](#database-schema)
- [Installation Guide](#installation-guide)
- [Login Credentials](#login-credentials)
- [User Roles & Permissions](#user-roles--permissions)
- [Features Overview](#features-overview)
- [Report Types](#report-types)
- [Security](#security)
- [Folder Structure](#folder-structure)
- [Customization](#customization)
- [Troubleshooting](#troubleshooting)

---

## Technology Stack

| Layer | Technology |
|-------|------------|
| **Frontend** | HTML5, CSS3 (single custom stylesheet), Vanilla JavaScript (ES6) |
| **Backend** | PHP 8+ (procedural with PDO database abstraction) |
| **Database** | MySQL 8+ / MariaDB 10+ (PDO prepared statements) |
| **Server** | Apache 2.4+ with `.htaccess` security rules |
| **Runtime** | XAMPP (Apache + MySQL + PHP) |
| **Dependencies** | None — zero external libraries, no Composer, no npm |

---

## Architecture Overview

### Application Flow

```
User → index.php
         ├─ Logged in? → dashboard.php → getDashboardRedirect() → {role}/index.php
         └─ Not logged in? → login.php
```

1. Every request hits `index.php` which checks session status and routes accordingly.
2. `dashboard.php` calls `getDashboardRedirect()` — a central function that maps the user's role to their dashboard (`admin/index.php`, `pharmacist/index.php`, or `staff/index.php`).
3. Every protected page includes `includes/auth.php` which:
   - Calls `requireLogin()` to verify an active session (with configurable timeout)
   - Calls `requireRole('admin'|'pharmacist'|'staff')` to enforce role-based access
4. Page rendering uses `includes/header.php` (opens HTML, renders sidebar + navbar) and `includes/footer.php` (closes HTML, renders toast notifications, includes `main.js`).

### Key Design Decisions

- **No framework** — entirely custom-built for performance, simplicity, and educational value.
- **Singleton database connection** — `Database::getInstance()->getConnection()` returns a single shared PDO instance per request.
- **Role-based routing** — each role has its own directory (`admin/`, `pharmacist/`, `staff/`) with its own set of pages. No shared controllers.
- **Unified report engine** — a single file `reports/generate.php` handles all 9 report types via a `switch` statement.
- **Automatic status calculation** — drug statuses (`available`, `low_stock`, `out_of_stock`, `expired`, `discontinued`) are computed from quantity, expiry date, and min_stock values — no manual toggling required.
- **Self-contained installer** — `database/init_db.php` reads `schema.sql` + `seed.sql` and executes them via PDO; no phpMyAdmin needed.

### How Pages Are Organized

```
Root pages:    index.php, login.php, logout.php, dashboard.php
Admin:         admin/{index,drugs,users,categories,settings,reports,notifications,audit_logs}.php
Pharmacist:    pharmacist/{index,drugs,checklist,stock_in,stock_out,reports,notifications}.php
Staff:         staff/{index,inventory,reports,notifications}.php
API:           api/{search,get_drug}.php
Reports:       reports/generate.php
```

### API Endpoints

| Endpoint | Method | Purpose | Returns |
|----------|--------|---------|---------|
| `api/search.php?q=...` | GET | Global navbar drug search (min 1 char, LIKE match on 4 fields, limit 10) | JSON array |
| `api/search.php?drug_id=N` | GET | Single drug lookup by ID | JSON array (1 row) |
| `api/get_drug.php?id=N` | GET | Full drug details + categories + suppliers + last 20 transactions | `{success, data}` JSON |

---

## Database Schema

11 tables with proper normalization, foreign keys, and cascading rules.

### `roles`
| Column | Type | Notes |
|--------|------|-------|
| id | INT AUTO_INCREMENT PK | 1=admin, 2=pharmacist, 3=staff |
| role_name | VARCHAR(50) UNIQUE | |
| description | VARCHAR(255) | |
| created_at | TIMESTAMP | |

### `users`
| Column | Type | Notes |
|--------|------|-------|
| id | INT AUTO_INCREMENT PK | |
| username | VARCHAR(50) UNIQUE | |
| email | VARCHAR(100) UNIQUE | |
| password | VARCHAR(255) | bcrypt hash |
| first_name, last_name | VARCHAR(50) | |
| phone | VARCHAR(20) | |
| role_id | INT FK → roles.id | ON DELETE RESTRICT |
| status | ENUM('active','inactive','suspended') | |
| last_login | DATETIME | |
| created_at, updated_at | TIMESTAMP | |

### `drug_categories`
| Column | Type | Notes |
|--------|------|-------|
| id | INT AUTO_INCREMENT PK | |
| category_name | VARCHAR(100) UNIQUE | e.g. Antibiotics, Analgesics |
| description | TEXT | |
| status | ENUM('active','inactive') | |
| created_at, updated_at | TIMESTAMP | |

### `suppliers`
| Column | Type | Notes |
|--------|------|-------|
| id | INT AUTO_INCREMENT PK | |
| supplier_name | VARCHAR(150) | |
| contact_person, email, phone | VARCHAR(100/20) | |
| address, city, state, zip_code, country | TEXT/VARCHAR | |
| status | ENUM('active','inactive') | |
| created_at, updated_at | TIMESTAMP | |

### `drugs` (core inventory table)
| Column | Type | Notes |
|--------|------|-------|
| id | INT AUTO_INCREMENT PK | |
| drug_code | VARCHAR(50) UNIQUE | Optional |
| drug_name | VARCHAR(200) | |
| generic_name | VARCHAR(200) | |
| brand_name | VARCHAR(200) | |
| category_id | INT FK → drug_categories.id | ON DELETE SET NULL |
| dosage_form | VARCHAR(100) | Tablet, Capsule, Syrup, etc. |
| strength | VARCHAR(100) | 500 mg, 250 mg/5 mL, etc. |
| batch_number | VARCHAR(100) UNIQUE | |
| manufacturer | VARCHAR(200) | |
| supplier_id | INT FK → suppliers.id | ON DELETE SET NULL |
| quantity | INT DEFAULT 0 | Current stock count |
| min_stock | INT DEFAULT 10 | Threshold for low-stock alert |
| unit_price | DECIMAL(10,2) | |
| expiry_date | DATE | |
| manufacturing_date | DATE | |
| storage_location | VARCHAR(100) | Shelf A-12, Cold Room, etc. |
| storage_condition | VARCHAR(100) | Room Temperature, Refrigerated |
| description | TEXT | |
| barcode | VARCHAR(100) | |
| status | ENUM('available','low_stock','out_of_stock','expired','discontinued') | Auto-calculated via triggers |
| created_by | INT FK → users.id | ON DELETE SET NULL |
| created_at, updated_at | TIMESTAMP | |

### `stock_transactions`
| Column | Type | Notes |
|--------|------|-------|
| id | INT AUTO_INCREMENT PK | |
| drug_id | INT FK → drugs.id | ON DELETE CASCADE |
| transaction_type | ENUM('stock_in','stock_out','adjustment') | |
| quantity | INT | Absolute count transacted |
| previous_quantity, new_quantity | INT | Quantity snapshots |
| unit_price | DECIMAL(10,2) | |
| reference_number | VARCHAR(100) | Auto: `STKIN-YYYYMMDD-XXXXXX` |
| remarks | TEXT | |
| performed_by | INT FK → users.id | ON DELETE RESTRICT |
| transaction_date | TIMESTAMP | |

### `drug_checklists`
| Column | Type | Notes |
|--------|------|-------|
| id | INT AUTO_INCREMENT PK | |
| drug_id | INT FK → drugs.id | ON DELETE CASCADE |
| checklist_status | ENUM('available','low_stock','out_of_stock','expired','damaged') | |
| remarks | TEXT | |
| checked_by | INT FK → users.id | ON DELETE RESTRICT |
| checked_date | DATE | |
| checked_time | TIME | |
| created_at | TIMESTAMP | |

### `notifications`
| Column | Type | Notes |
|--------|------|-------|
| id | INT AUTO_INCREMENT PK | |
| user_id | INT FK → users.id | ON DELETE CASCADE |
| notification_type | ENUM('expiry_alert','low_stock','out_of_stock','expired','system','info') | |
| title | VARCHAR(200) | |
| message | TEXT | |
| related_drug_id | INT FK → drugs.id | ON DELETE SET NULL |
| is_read | TINYINT(1) DEFAULT 0 | |
| created_at | TIMESTAMP | |

### `audit_logs`
| Column | Type | Notes |
|--------|------|-------|
| id | INT AUTO_INCREMENT PK | |
| user_id | INT FK → users.id | ON DELETE SET NULL |
| action | VARCHAR(100) | login, logout, create, update, delete, stock_in, stock_out, checklist, toggle_status |
| entity_type | VARCHAR(50) | user, drug, category, drug_checklist, stock_transaction, settings |
| entity_id | INT | |
| description | TEXT | Human-readable |
| ip_address | VARCHAR(45) | IPv4/IPv6 |
| user_agent | TEXT | |
| created_at | TIMESTAMP | |

### `system_settings`
| Column | Type | Notes |
|--------|------|-------|
| id | INT AUTO_INCREMENT PK | |
| setting_key | VARCHAR(100) UNIQUE | |
| setting_value | TEXT | |
| description | VARCHAR(255) | |
| updated_by | INT FK → users.id | |
| updated_at | TIMESTAMP | |

### `user_sessions`
| Column | Type | Notes |
|--------|------|-------|
| id | INT AUTO_INCREMENT PK | |
| user_id | INT FK → users.id | ON DELETE CASCADE |
| session_token | VARCHAR(255) UNIQUE | |
| ip_address | VARCHAR(45) | |
| user_agent | TEXT | |
| last_activity | DATETIME | |
| is_active | TINYINT(1) | |
| created_at | TIMESTAMP | |

### Key Relationships

```
users.role_id → roles.id
drugs.category_id → drug_categories.id
drugs.supplier_id → suppliers.id
drugs.created_by → users.id
stock_transactions.drug_id → drugs.id
stock_transactions.performed_by → users.id
drug_checklists.drug_id → drugs.id
drug_checklists.checked_by → users.id
notifications.user_id → users.id
notifications.related_drug_id → drugs.id
audit_logs.user_id → users.id
system_settings.updated_by → users.id
```

---

## Installation Guide

### Step 1: Install XAMPP

Download and install XAMPP from [https://www.apachefriends.org/](https://www.apachefriends.org/)

### Step 2: Start Apache & MySQL

Open XAMPP Control Panel and click **Start** for both **Apache** and **MySQL**.

### Step 3: Copy Project Files

```
Copy the entire Drug-Checklist-System folder to:
C:\xampp\htdocs\Drug-Checklist-System\
```

### Step 4: Configure Database (Optional)

If your MySQL credentials differ from the defaults, edit `config/database.php`:

```
Host:      localhost
Database:  drug_checklist_system
Username:  root
Password:  (empty — default for fresh XAMPP)
Port:      3306
```

### Step 5: Run Database Installer

Open your browser and navigate to:

```
http://localhost/Drug-Checklist-System/database/init_db.php
```

Click **Install Database**. The installer reads `schema.sql` (creates 11 tables) and `seed.sql` (inserts default roles, users, categories, suppliers, and 30 sample drugs).

### Step 6: Login

```
http://localhost/Drug-Checklist-System/login.php
```

---

## Login Credentials

| Role | Username | Password |
|------|----------|----------|
| **Administrator** | `admin` | `admin123` |
| **Pharmacist** | `pharmacist` | `pharmacist123` |
| **Healthcare Staff** | `staff` | `staff123` |

> **Security**: Change these passwords immediately after first login. Use `database/generate_hashes.php` to create new bcrypt hashes, then update `database/seed.sql` and re-run the installer.

---

## User Roles & Permissions

### Administrator (8 pages, 9 report types)
Full system ownership:
- **Dashboard**: 8 stat cards — Total Drugs, Available, Low Stock, Out of Stock, Expired, Discontinued, Active Drugs, Categories — plus recent transactions and checklists tables.
- **Drug Management**: Full CRUD (18-field form), status toggle, batch delete with confirmation, paginated table with search/category/status filters, auto-creates initial stock-in on drug creation.
- **User Management**: Full CRUD with role assignment, status toggle (active/inactive/suspended), password reset, self-delete prevention, paginated table with last login timestamps.
- **Category Management**: Full CRUD, status toggle, delete blocked if drugs reference the category, drug-count display per category.
- **System Settings**: Edit all 8 settings (system name, session timeout, expiry alert days, low stock threshold, currency, timezone, company info).
- **Reports Dashboard**: All 9 report types (inventory, expired, low stock, out of stock, stock movement, daily checklist, monthly checklist, user activities, audit logs).
- **Notification Center**: Filter by type and read status, auto-marks read on visit, paginated.
- **Audit Logs**: Full viewer with user/action/date-range filters, paginated showing IP and user agent.
- **CSV & Print export** on all reports.

### Pharmacist (7 pages, 7 report types)
Drug management operations:
- **Dashboard**: 5 stat cards — Available, Low Stock, Out of Stock, Expired, Today's Checklists — plus low-stock alerts table, recent stock-in/out tables, and today's checklists summary.
- **Drug Management**: Same CRUD as admin (drugs.php accepts both admin and pharmacist roles).
- **Drug Checklist**: Record checklist entries (available/low_stock/out_of_stock/expired/damaged). Auto-updates drug status on submission (expired → status=expired, out_of_stock → quantity=0, damaged → status=discontinued). Date-filtered paginated history.
- **Stock In**: Add quantity with `SELECT ... FOR UPDATE` row-level locking, auto-generated reference numbers, optional price update. Paginated history table.
- **Stock Out**: Remove quantity with real-time JS validation (prevents negative stock), mandatory remarks, `SELECT ... FOR UPDATE`. Paginated history table.
- **Reports Dashboard**: 7 report types (no user activities or audit logs).
- **Notification Center**: Same filter/pagination as admin.

### Healthcare Staff (4 pages, 5 report types)
Read-only access:
- **Dashboard**: 4 stat cards — Available, Low Stock, Out of Stock, Expired — plus drug inventory overview and recent checklists tables.
- **View Inventory**: Read-only drug viewer with search (name/generic/batch), category/status filters, detail modal via API. Discontinued drugs hidden.
- **Reports Dashboard**: 5 report types (inventory, expired, low stock, out of stock, daily checklist).
- **Notification Center**: Same as admin/pharmacist.

---

## Features Overview

### Dashboard Analytics (Real-Time)

Each dashboard queries the database on every page load — no caching. Each stat card is a link to the filtered detail view.

**Admin Dashboard** (`admin/index.php`):
- 8 stat cards: Total, Available, Low Stock (`quantity <= min_stock AND quantity > 0`), Out of Stock (`quantity=0 OR status='out_of_stock'`), Expired (`status='expired'`), Discontinued (`status='discontinued'`), Active Drugs (total minus expired/discontinued), Categories (total active categories)
- Tables: Recent Stock Transactions (last 10), Recent Drug Checklists (last 10)

**Pharmacist Dashboard** (`pharmacist/index.php`):
- 5 stat cards: Available, Low Stock, Out of Stock, Expired, Today's Checklists
- Tables: Low Stock Alerts (top 10), Recent Stock In (last 5), Recent Stock Out (last 5), Today's Checklists

**Staff Dashboard** (`staff/index.php`):
- 4 stat cards: Available, Low Stock, Out of Stock, Expired
- Tables: Drug Inventory Overview (last 10), Recent Checklists (last 10)

### Drug Management

Complete CRUD interface with 18 fields per drug record:

| Field | Description |
|-------|-------------|
| Drug Name | Required, display name |
| Generic Name | Active pharmaceutical ingredient |
| Brand Name | Commercial brand |
| Drug Code | Optional unique code |
| Category | Dropdown from `drug_categories` |
| Dosage Form | Tablet, Capsule, Syrup, Injection, etc. |
| Strength | 500 mg, 250 mg/5 mL, etc. |
| Batch Number | Required, unique identifier |
| Manufacturer | Manufacturer name |
| Supplier | Dropdown from `suppliers` |
| Quantity | Current stock count |
| Min Stock | Low-stock threshold |
| Unit Price | Price per unit |
| Expiry Date | Must be a future date |
| Manufacturing Date | |
| Storage Location | e.g. Shelf A-12, Cold Room |
| Storage Condition | Room Temperature, Refrigerated, etc. |
| Barcode | Optional |
| Description | Free text notes |

**Status auto-calculation logic:**
```
IF quantity = 0               → out_of_stock
IF status = 'expired'         → expired (set by checklist or expiry check)
IF quantity <= min_stock      → low_stock (and not expired/discontinued)
IF quantity > min_stock       → available
```

### Inventory Transactions

Stock operations use database transactions with row-level locking (`SELECT ... FOR UPDATE`) to prevent race conditions:

- **Stock In** (`pharmacist/stock_in.php`): Add quantity, auto-generate reference number (`STKIN-YYYYMMDD-XXXXXX`), optional price update, auto-recalculates drug status.
- **Stock Out** (`pharmacist/stock_out.php`): Remove quantity with validation against current stock (enforced in JS + PHP), mandatory remarks, auto-recalculates drug status. New quantity cannot go below 0.
- Full audit trail: every transaction records previous_quantity, new_quantity, performed_by, and a human-readable description is logged in `audit_logs`.

### Drug Checklist

Pharmacists perform periodic checks on drugs and record the results. Checklist options:

| Status | Meaning | Effect on Drug |
|--------|---------|----------------|
| Available | Good condition, sufficient stock | No change |
| Low Stock | Running low | Updates drug status to `low_stock` |
| Out of Stock | Depleted | Sets quantity to 0, status to `out_of_stock` |
| Expired | Past expiry date | Updates drug status to `expired` |
| Damaged | Unusable condition | Updates drug status to `discontinued` |

Each entry records: drug, status, remarks, checker name, date, and time. A notification is sent to all admins and pharmacists upon creation.

### Automatic Notifications

The `checkAndCreateNotifications()` function runs automatically on admin and pharmacist dashboard loads. It performs three checks:

1. **Expiring Soon**: Scans for drugs expiring within `expiry_alert_days` (configurable in Settings). Creates `expiry_alert` notifications.
2. **Expired**: Finds drugs past their expiry date that haven't been marked expired yet. Auto-updates status to `expired`, creates notifications.
3. **Low Stock / Out of Stock**: Finds drugs where `quantity <= min_stock` and auto-updates status. Creates `low_stock` or `out_of_stock` notifications.

Notifications appear as:
- A badge counter on the navbar bell icon (capped at 99+)
- A dedicated notification center page per role with type/read-status filters and pagination

### Global Search

The navbar search (debounced 300ms) queries `api/search.php` which performs a `LIKE` search across:
- `drug_name`
- `generic_name`
- `brand_name`
- `batch_number`

Results render as a dropdown with drug name, batch number, quantity, and status. Each result links to the appropriate detail page based on the user's role (admin → `admin/drugs.php`, pharmacist → `pharmacist/drugs.php`, staff → `staff/inventory.php`).

### Reports (9 types)

All reports are generated by a single unified engine (`reports/generate.php`). Role-based access determines which report types are available:

| # | Report Type | Admin | Pharm. | Staff | Data Source |
|---|-------------|-------|--------|-------|-------------|
| 1 | Inventory Report | ✓ | ✓ | ✓ | drugs, categories, suppliers |
| 2 | Expired Drugs Report | ✓ | ✓ | ✓ | drugs (expired status) |
| 3 | Low Stock Report | ✓ | ✓ | ✓ | drugs (qty <= min_stock) |
| 4 | Out of Stock Report | ✓ | ✓ | ✓ | drugs (qty=0 or out_of_stock) |
| 5 | Stock Movement Report | ✓ | ✓ | ✗ | stock_transactions, drugs, users |
| 6 | Daily Checklist Report | ✓ | ✓ | ✓ | drug_checklists, drugs, users |
| 7 | Monthly Checklist Report | ✓ | ✓ | ✗ | drug_checklists (SUM/GROUP BY) |
| 8 | User Activities Report | ✓ | ✗ | ✗ | audit_logs, users, roles |
| 9 | Audit Logs Report | ✓ | ✗ | ✗ | audit_logs, users |

Features: date range filtering, paginated tables (20/page), CSV export, print support.

### Helpers & Utilities (`includes/functions.php`)

| Function | Purpose |
|----------|---------|
| `db()` | Returns singleton PDO connection |
| `sanitize($input)` | `htmlspecialchars(strip_tags(trim()))` |
| `redirect($url)` | Prepends BASE_URL, sends Location header |
| `jsonResponse($data, $code)` | JSON response with status code |
| `formatDate()`, `formatDateTime()` | Date/time formatting |
| `formatCurrency($amount)` | Formats with currency symbol from settings |
| `timeAgo($datetime)` | Relative time: "just now", "5 min ago", etc. |
| `getStatusBadge($status)` | Returns colored `<span>` badge HTML |
| `getSetting($key, $default)` | Reads from `system_settings` table |
| `generateReferenceNumber($prefix)` | Creates `PREFIXYYYYMMDD-XXXXXX` |
| `logAudit()` | Inserts into `audit_logs` with IP + user agent |
| `createNotification()` | Inserts into `notifications` |
| `notifyAllAdminsAndPharmacists()` | Sends to all active role_id 1 or 2 users |
| `getPaginatedData()` | Generic paginated query builder |
| `renderPagination()` | Renders page navigation HTML |
| `checkAndCreateNotifications()` | Auto-scan for expiring/expired/low-stock drugs |
| `getCurrentUserId()` | Returns session user ID |
| `getCurrentUserRole()` | Returns session role name |
| `getDashboardRedirect()` | Maps role → dashboard URL |
| `loginUser()`, `logoutUser()` | Auth lifecycle |
| `requireLogin()`, `requireRole()` | Access control |
| `checkSessionTimeout()` | Configurable idle timeout enforcement |
| `getCsrfToken()`, `verifyCsrfToken()` | CSRF protection |

---

## Security

| Measure | Implementation |
|---------|---------------|
| **SQL Injection** | All queries use PDO prepared statements with `ATTR_EMULATE_PREPARES => false` |
| **XSS Prevention** | All output through `sanitize()` in PHP; `escapeHtml()` in JavaScript |
| **CSRF Protection** | 64-char hex token generated per session, verified on all forms and destructive GET actions |
| **Password Storage** | bcrypt via `password_hash(PASSWORD_DEFAULT)` with auto-rehashing via `password_needs_rehash()` |
| **Session Security** | HTTP-only cookies, SameSite=Strict, strict mode enabled, configurable timeout |
| **Authorization** | `requireRole()` on every page; role checked server-side before any operation |
| **Audit Trail** | Every create/update/delete/login/logout/stock operation logged with user, action, entity, IP, user agent |
| **Row-Level Locking** | Stock in/out use `SELECT ... FOR UPDATE` within transactions |
| **Directory Protection** | `.htaccess` blocks access to config/, includes/, database/, logs/ directories |
| **File Type Protection** | `.htaccess` blocks .sql, .md, .log file access |

---

## Folder Structure

```
Drug-Checklist-System/
├── .htaccess                     # Apache security rules
├── index.php                     # Entry point → login or dashboard
├── login.php                     # Authentication form
├── logout.php                    # Session destruction
├── dashboard.php                 # Role-based routing
│
├── admin/                        # Admin module (8 pages)
│   ├── index.php                 # Dashboard
│   ├── drugs.php                 # Drug CRUD
│   ├── users.php                 # User management
│   ├── categories.php            # Category management
│   ├── settings.php              # System settings
│   ├── reports.php               # Reports dashboard
│   ├── notifications.php         # Notification center
│   └── audit_logs.php            # Audit log viewer
│
├── pharmacist/                   # Pharmacist module (7 pages)
│   ├── index.php                 # Dashboard
│   ├── drugs.php                 # Drug CRUD
│   ├── checklist.php             # Drug checklist
│   ├── stock_in.php              # Stock receiving
│   ├── stock_out.php             # Stock dispensing
│   ├── reports.php               # Reports dashboard
│   └── notifications.php         # Notification center
│
├── staff/                        # Staff module (4 pages)
│   ├── index.php                 # Dashboard
│   ├── inventory.php             # Read-only inventory
│   ├── reports.php               # Reports dashboard
│   └── notifications.php         # Notification center
│
├── api/                          # JSON endpoints
│   ├── search.php                # Drug search API
│   └── get_drug.php              # Drug details API
│
├── reports/
│   └── generate.php              # Unified report engine (9 types)
│
├── includes/                     # Shared components
│   ├── auth.php                  # Auth functions
│   ├── functions.php             # All helper functions (~30)
│   ├── header.php                # HTML head + sidebar + navbar
│   ├── footer.php                # HTML close + toasts
│   ├── sidebar.php               # Role-based navigation
│   └── navbar.php                # Top bar with search + notifications
│
├── config/
│   ├── config.php                # Session config, BASE_URL, CSRF, timezone
│   └── database.php              # PDO singleton class
│
├── database/
│   ├── schema.sql                # Full DDL (11 tables)
│   ├── seed.sql                  # Demo data (3 users, 12 categories, 17 suppliers, 30 drugs)
│   ├── init_db.php               # Web-based installer
│   └── generate_hashes.php       # bcrypt hash generator CLI
│
├── assets/
│   ├── css/style.css             # Single 449-line stylesheet
│   └── js/main.js                # Client-side JavaScript (176 lines)
│
├── logs/                         # PHP error logs
└── uploads/                      # Reserved for future file uploads
```

---

## Customization

### Via Admin Settings Panel

1. Login as admin → **Settings** in the sidebar
2. Modify any of these:

| Setting | Key | Default | Description |
|---------|-----|---------|-------------|
| System Name | `system_name` | Drug Checklist Healthcare System | Browser title, report headers |
| Session Timeout | `session_timeout` | 3600 | Seconds before auto-logout |
| Expiry Alert Days | `expiry_alert_days` | 30 | Days before expiry to trigger alerts |
| Low Stock Threshold | `low_stock_threshold` | 10 | Default min_stock for new drugs |
| Currency | `currency` | # (Naira) | Symbol used in `formatCurrency()` |
| Timezone | `timezone` | Asia/Manila | System timezone |
| Company Address | `company_address` | (empty) | Report footer |
| Company Phone | `company_phone` | (empty) | Report footer |

### Via Database

Settings are stored as key-value pairs in `system_settings`. Edit directly via phpMyAdmin or:
```sql
UPDATE system_settings SET setting_value = 'New Name' WHERE setting_key = 'system_name';
```

### Per-Drug Thresholds

Each drug has its own `min_stock` field (default 10). Low-stock alerts trigger when `quantity <= min_stock` regardless of the global threshold setting.

---

## Troubleshooting

### Blank page on login

Check logs:
- Apache: `C:\xampp\apache\logs\error.log`
- PHP: `Drug-Checklist-System\logs\error.log`

Ensure `logs\` directory is writable by the web server.

### Database connection error

1. Verify MySQL is running in XAMPP Control Panel
2. Check credentials in `config/database.php`
3. Ensure database `drug_checklist_system` exists and is accessible
4. Verify MySQL is on port 3306 (default)

### 404 errors on links

Ensure the project folder name matches the URL path exactly:
```
Project: C:\xampp\htdocs\Drug-Checklist-System\
URL:     http://localhost/Drug-Checklist-System/
```

The `config/config.php` defines `define('BASE_URL', '/Drug-Checklist-System/');` — update this if your folder name differs.

### "You do not have permission" on search results

The global search result links are role-aware. If you see this, ensure you're logged in as the correct role. Admin and staff users are routed to their own detail pages, not the pharmacist page.

### Forgot password

Generate a new bcrypt hash:
```bash
cd C:\xampp\htdocs\Drug-Checklist-System\database
php generate_hashes.php
```

Copy the output hash and update the `users` table:
```sql
UPDATE users SET password = 'paste_hash_here' WHERE username = 'admin';
```

### Reinstall Database (Reset Everything)

Visit `http://localhost/Drug-Checklist-System/database/init_db.php` and click **Install Database**. This drops all existing tables and recreates them with fresh seed data.

---

## Architecture: How It's Built

### Frontend (No Dependencies)

The entire UI is hand-crafted HTML5 + CSS3 + ES6 JavaScript — no Bootstrap, no jQuery, no Tailwind, no npm packages.

- **`assets/css/style.css`** (449 lines): Single stylesheet covering layout (flexbox sidebar + content), navigation components, stat cards, tables, forms, modals, badges, toast notifications, pagination, responsive breakpoints, and print styles. Uses CSS variables for theming.
- **`assets/js/main.js`** (176 lines): Vanilla JS handling sidebar toggle, user dropdown, toast auto-dismiss, debounced global search (XHR fetch), modal controls, custom confirmation dialogs, form validation, and double-submit prevention.

### Backend (Procedural PHP + PDO)

No framework. The architecture follows a simple include-based pattern:

- **Configuration layer**: `config/config.php` (session setup, CSRF, timezone, BASE_URL) + `config/database.php` (PDO singleton)
- **Auth layer**: `includes/auth.php` (~15 functions for login, logout, session timeout, role checks)
- **Helper layer**: `includes/functions.php` (~30 functions for formatting, database, pagination, notifications, audit logging)
- **Presentation layer**: `includes/header.php` + `includes/footer.php` + `includes/sidebar.php` + `includes/navbar.php` (shared UI)
- **Page layer**: Role-specific directories (`admin/`, `pharmacist/`, `staff/`) — each page requires auth, fetches data, includes header/footer
- **API layer**: `api/search.php` and `api/get_drug.php` — lightweight JSON endpoints
- **Report layer**: `reports/generate.php` — single file, 9 report types via switch, role-checked, CSV + print

### Database (11 Tables, Normalized)

All tables use InnoDB with foreign keys and cascading rules. The schema is self-contained in `database/schema.sql`. The installer (`database/init_db.php`) runs it via PDO with transaction safety.

### Key Patterns

- **Every page** follows the same include pattern: `requireLogin` / `requireRole` → fetch data → `include header` → render HTML → `include footer`
- **Every form** includes a hidden CSRF token; every destructive action verifies it
- **Every user action** that modifies data is logged via `logAudit()`
- **Stock operations** use database transactions with `SELECT ... FOR UPDATE` for concurrency safety
- **Notifications** are created both reactively (on stock changes, checklist submissions) and proactively (on dashboard load via `checkAndCreateNotifications()`)
- **Pagination** is handled by `getPaginatedData()` which returns structured pagination metadata rendered by `renderPagination()`

---

## License

This project is developed as a healthcare management system suitable for academic projects and real-world healthcare inventory management.
