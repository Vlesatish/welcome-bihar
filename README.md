# Welcome Bihar (वेलकम बिहार)
### Unified District & Block Citizen Services Data Portal

Welcome Bihar is a robust, production-ready web application built in **PHP 8.2+ / PDO / MySQL & MariaDB** designed to collect, manage, and process three configurable types of citizen data across all **38 districts and 708 blocks/urban local bodies** of Bihar.

The application features a clean, responsive, mobile-first dashboard architecture, native **bilingual support (Hindi default & English)**, an **immutable double-entry wallet ledger**, role-based permission enforcement, dynamic form builders, and **ALL API UPI Scan & Pay QR code integration**.

---

## 1. Technology Stack & Prerequisites

- **Backend**: PHP 8.2+ (Compatible with PHP 8.0+)
- **Database**: MySQL 8.0+ or MariaDB 10.3+ (Fully manageable and importable via phpMyAdmin)
- **Database Driver**: PDO with `utf8mb4` charset and prepared statements
- **Frontend**: HTML5, CSS3, Vanilla JavaScript (ES6+), Bootstrap 5.3, Bootstrap Icons
- **Typography**: Google Fonts (*Noto Sans Devanagari* & *Outfit*)
- **Server**: Apache (with `mod_rewrite` enabled) or Nginx, or PHP CLI Built-in Server

---

## 2. System Architecture & Folder Layout

```
welcome bihar/
│
├── .env.example                  # Template environment variables (safe placeholders only)
├── .env                          # Local environment configuration (never commit secrets)
├── README.md                     # Comprehensive operational and architecture guide
│
├── app/
│   ├── Config/
│   │   ├── Config.php            # Central application constants, session, and locale config
│   │   ├── Database.php          # Singleton PDO database connection manager
│   │   └── Env.php               # Lightweight DotEnv configuration reader
│   │
│   ├── Controllers/
│   │   ├── AdminController.php   # System dashboard, user management, and manual adjustments
│   │   ├── ApplicationController.php # Wizard, submissions, list, details, and CSV export
│   │   ├── AuthController.php    # Authentication, registration, logout, and language switch
│   │   ├── DataTypeController.php# Configurable data types and dynamic field builder
│   │   ├── DistributorController.php # Distributor team, scoped applications, and review
│   │   ├── LocationController.php# Cascading blocks API, district/block manager, and CSV import/export
│   │   ├── ReconciliationController.php # Payment order reconciliation and manual audit flow
│   │   ├── SettingsController.php# System branding, workflow toggles, and ALL API configuration
│   │   ├── UserController.php    # Operator dashboard, statistics, and profile management
│   │   └── WalletController.php  # Passbook, UPI QR generation, status polling, and webhooks
│   │
│   ├── helpers.php               # Global root-namespace helpers: __(), e(), csrf_token(), csrf_field()
│   │
│   ├── Services/
│   │   ├── AllApiProvider.php    # ALL API adapter (QR generation, status check, sandbox fallback)
│   │   ├── ApplicationService.php# Application lifecycle, duplicate checks, fees, and status engine
│   │   ├── AuditService.php      # Structured administrative and security event logging
│   │   ├── AuthService.php       # Authentication, CSRF validation, and location scope verification
│   │   ├── CsvService.php        # CSV export and import with UTF-8 BOM encoding for Hindi text
│   │   ├── PaymentProviderInterface.php # Clean contract for UPI QR code providers
│   │   ├── TranslationService.php# Bilingual dictionary with Hindi as default locale
│   │   └── WalletService.php     # Immutable ledger, row locking (FOR UPDATE), and idempotency
│   │
│   └── Views/
│       ├── layouts/              # Header, footer, navbar, language toggle, and 404 views
│       ├── auth/                 # Login and Kiosk operator registration views
│       ├── user/                 # Operator dashboard, wizard, list, detail, topup, QR, passbook
│       ├── distributor/          # Distributor dashboard, team view, and team applications
│       └── admin/                # Admin command center, users, locations, data types, reconciliation
│
├── cli/
│   ├── build_seed.php            # Seed data compiler from RTPS/BOCW datasets
│   ├── create_admin.php          # Secure administrative account generator and password reset utility
│   └── test_suite.php            # 15-step automated system verification suite
│
├── database/
│   ├── schema.sql                # Production phpMyAdmin-importable InnoDB schema
│   └── seed.sql                  # Complete dataset (38 districts, 708 blocks, 3 data types, settings)
│
├── public/
│   ├── index.php                 # Front controller and application router
│   ├── .htaccess                 # Apache rewrite engine configuration
│   └── assets/
│       ├── css/custom.css        # Rich government theme, glassmorphism cards, responsive typography
│       └── js/app.js             # Cascading dropdowns, live UPI status polling, and validation
│
└── storage/
    ├── .htaccess                 # Direct access prevention (Deny from all)
    ├── logs/                     # Sanitized system error logs
    └── uploads/                  # Protected citizen documents
```

---

## 3. Database Installation (phpMyAdmin & CLI)

### Method A: phpMyAdmin Web Import
1. Open your phpMyAdmin dashboard (e.g., `http://localhost/phpmyadmin`).
2. Create a new database named `welcome_bihar` with collation `utf8mb4_unicode_ci`.
3. Select the `welcome_bihar` database.
4. Click on the **Import** tab:
   - First, select and import `database/schema.sql`.
   - Next, select and import `database/seed.sql`.
5. All 17 tables, 38 districts, 708 blocks, 3 services, dynamic fields, and default settings will be created.

### Method B: Command Line (CLI) Import
```bash
# Log in to MySQL and create database
mysql -u root -p -e "CREATE DATABASE IF NOT EXISTS welcome_bihar CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# Import schema and seed data
mysql -u root -p welcome_bihar < database/schema.sql
mysql -u root -p welcome_bihar < database/seed.sql
```

---

## 4. Default Seed Accounts & Credentials

The seed data creates 3 demo accounts for immediate access. All passwords are encrypted with standard Bcrypt (`password_hash`):

| Role | Email | Mobile | Default Password | Initial Wallet | Scope |
| :--- | :--- | :--- | :--- | :--- | :--- |
| **Admin** | `admin@welcomebihar.gov.in` | `9999900001` | `Welcome@2026` | ₹0.00 | State-wide (All 38 Districts) |
| **Distributor** | `distributor@welcomebihar.gov.in` | `9999900002` | `Welcome@2026` | ₹500.00 | Patna & Nalanda Regional Scope |
| **Operator (User)**| `operator@welcomebihar.gov.in` | `9999900003` | `Welcome@2026` | ₹1,000.00 | Danapur Kiosk (Patna District) |

### Creating a New Secure Admin via CLI
You can create or update an administrator account without exposing passwords in source files:
```bash
php cli/create_admin.php "Super Admin" "9999900001" "admin@welcomebihar.gov.in" "YourSecurePasswordHere"
```

---

## 5. Three Configurable Data Types

The system comes pre-configured with 3 representative citizen data types. Administrators can rename, enable, disable, adjust fees, commissions, or customize dynamic fields at `/admin/data-types`:

1. **BOCW Shramik Registration (बिहार भवन एवं सन्निर्माण कर्मकार निबंधन)**
   - **Default Fee**: ₹50.00 | **Distributor Commission**: ₹10.00
   - **Key Fields**: Applicant Name, Father/Husband Name, Gender, Date of Birth, Mobile, 12-digit Aadhaar Number (Unique duplicate check), Trade/Work Nature, 90-day certification, Bank Account Number, IFSC Code, Address.
2. **Ration Card Family Census (राशन कार्ड परिवार विवरण सत्यापन)**
   - **Default Fee**: ₹30.00 | **Distributor Commission**: ₹5.00
   - **Key Fields**: Head of Family, Ration Card Number (Unique duplicate check), Mobile, Head Aadhaar, Total Family Members, Card Category (PHH, AAY, BPL), FPS Dealer Name, Village/Ward.
3. **RTPS Citizen Certificate Assistance (आरटीपीएस नागरिक सेवा सहायता)**
   - **Default Fee**: ₹40.00 | **Distributor Commission**: ₹8.00
   - **Key Fields**: Applicant Name, Certificate Type (Income, Caste, Residential, Non-Creamy Layer, EWS), Mobile, Aadhaar, Estimated Annual Income, Purpose, Target Issuing Authority Level (CO/RO, SDO, DM).

---

## 6. Bihar Districts & Blocks Data

- Pre-loaded with all **38 Districts** of Bihar and all **708 administrative Blocks, Nagar Panchayats, Nagar Parishads, and Municipal Corporations**.
- **Cascading Selection**: Selecting a district automatically loads blocks via the `/api/blocks?district_id=X` JSON endpoint.
- **Server-side Security**: Operators and distributors can only submit or view applications for districts/blocks granted in `location_permissions`.
- **Location Maintenance**: Administrators can add/edit districts and blocks, export the location catalog to CSV, or import updated district/block CSVs at `/admin/locations`.

---

## 7. Wallet & Immutable Ledger Engine

- **Transactional Integrity**: Uses database transactions with pessimistic row-level locking (`SELECT ... FOR UPDATE`) in `WalletService.php`.
- **Negative Balance Defense**: Every debit checks `balance >= amount` within the lock. Unauthorized negative balances are mathematically prevented.
- **Idempotency Keys**: All credits and debits use unique idempotency keys (`idempotency_key` unique constraint) preventing duplicate charges or replay attacks.
- **Immutable Ledger**: Transactions in `wallet_transactions` cannot be edited or deleted. Errors require compensating ledger entries.
- **Transaction Types**:
  - `TOPUP_CREDIT`: Automatic credit following verified UPI payment.
  - `APPLICATION_FEE_DEBIT`: Instant deduction upon application submission.
  - `COMMISSION_CREDIT`: Instant credit to supervising distributor upon submission.
  - `REFUND_CREDIT`: Automatic refund to operator if application is rejected (if configured).
  - `ADMIN_ADJUSTMENT`: Manual adjustment by administrator with mandatory audit reason.

---

## 8. ALL API UPI Scan & Pay QR Integration

### Exact Provider Nature & Disclaimer
> **IMPORTANT ARCHITECTURAL DISCLOSURE**:
> ALL API provides dynamic UPI Scan & Pay QR code generation and order/QR status tracking. It is **NOT** a payment gateway, bank, fund holder, settlement service, or UPI merchant account.
> The merchant must connect and configure their own eligible UPI merchant account with ALL API.

### Integration Flow
1. **Initiation**: User specifies a top-up amount (₹50 to ₹25,000) at `/wallet/topup`.
2. **Order Creation**: Server generates an internal unique order ID (`ORD-YYYYMMDDHHMMSS-XXXXX`) and inserts a `PENDING` record in `topup_orders`.
3. **QR Generation**: Server calls ALL API (`POST https://allapi.in/order/create`) with minimum customer data and internal order reference.
4. **Display**: The generated QR code and exact amount are displayed to the user with an animated scanner pulse border and clear payment instructions.
5. **Real-time Polling**: Client JavaScript polls `/wallet/topup/status?order_id=X` every 3.5 seconds.
6. **Server Verification**: The server checks status using ALL API's documented endpoint (`POST https://allapi.in/order/status`) or incoming server webhook (`POST /api/webhook/allapi`).
7. **Atomic Credit**: On verified success (`results.status === 'PAID'` / `'SUCCESS'`), the server marks the order `PAID` and credits the wallet exactly once with an immutable transaction row.
8. **Sandbox / Simulation**: If `ALLAPI_MODE=SANDBOX` or when testing offline, a deterministic sandbox QR is generated for seamless UI development.
9. **Reconciliation**: Administrators can review all top-up orders at `/admin/reconciliation`, re-query provider live status, or record authorized manual audit decisions.

### Environment Configuration
Configure these keys in `.env` or in **System Settings** (`/admin/settings`):
```env
ALLAPI_BASE_URL=https://allapi.in
ALLAPI_CREATE_ENDPOINT=/order/create
ALLAPI_STATUS_ENDPOINT=/order/status
ALLAPI_TOKEN=your_official_allapi_merchant_token
ALLAPI_MODE=SANDBOX          # Set to LIVE in production
ALLAPI_REDIRECT_URL=http://localhost:8080/wallet/topup/callback
ALLAPI_TIMEOUT_SECONDS=10
```

---

## 9. Security & Hardening Checklist

- [x] **Password Protection**: Passwords hashed with standard Bcrypt (`PASSWORD_BCRYPT`).
- [x] **SQL Injection Defense**: 100% PDO prepared statements with parameter binding; emulated prepares disabled.
- [x] **CSRF Protection**: Cryptographically secure token generated in session and validated on all state-changing POST requests (`csrf_field()`).
- [x] **Brute-Force Throttling**: IP and account login rate-limiting logged in `login_attempts` (locks out after 5 consecutive failures for 15 minutes).
- [x] **Session Fixation Defense**: `session_regenerate_id(true)` executed upon successful authentication.
- [x] **XSS Defense**: Strict HTML entity escaping via `e()` helper across all dynamic output.
- [x] **Storage Protection**: `.htaccess` placed in `/storage` with `Require all denied` to prevent direct document/log downloads.
- [x] **Credential Hygiene**: API tokens and secrets stored server-side only in protected configuration; never exposed in client JS, HTML, or logs.

---

## 10. Running the Automated Test Suite

A built-in 15-point automated verification test suite tests the database, authentication, wallet ledger atomicity, negative balance guard, application submission, dynamic fields, duplicate prevention, and ALL API adapter:

```bash
php cli/test_suite.php
```

Sample output:
```
========================================================
WELCOME BIHAR - SYSTEM VERIFICATION SUITE
========================================================
 [PASS] MySQL Database connection established via PDO
 [PASS] All 38 Bihar Districts loaded (Count: 38)
 [PASS] All 708 Administrative Blocks/Urban Bodies loaded (Count: 708)
 [PASS] 3 Core Data Types seeded (Count: 3)
 [PASS] Configurable Dynamic Fields seeded (Count: 26)
 [PASS] Demo Operator account exists
 [PASS] Password hash verification matches 'Welcome@2026'
 [PASS] Wallet credit of ₹250.00 executed atomically
 [PASS] Wallet debit of ₹100.00 executed atomically
 [PASS] Negative balance correctly prevented with Insufficient Balance exception
 [PASS] Application submission succeeded with Reference WB-20261011-XXXXX
 [PASS] Duplicate Aadhaar application correctly blocked
 [PASS] Transition to UNDER_REVIEW succeeded
 [PASS] Rejection succeeded and fee ₹50.00 was automatically refunded to wallet
 [PASS] ALL API QR generator created valid payment payload
========================================================
SUMMARY: Passed: 15 | Failed: 0
========================================================
ALL TESTS PASSED SUCCESSFULLY!
```

---

## 11. Local Development Quickstart

To run the application locally using PHP's built-in web server:

```bash
# 1. Start MySQL daemon (or start via XAMPP Control Panel)
# 2. Start PHP server pointing to public/ directory
php -S 127.0.0.1:8080 -t public

# 3. Open browser and visit:
http://127.0.0.1:8080/login
```
