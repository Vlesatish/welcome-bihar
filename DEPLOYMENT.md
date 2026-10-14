# Welcome Bihar - Production Deployment & Operations Guide

This comprehensive guide details the step-by-step procedures to deploy, configure, secure, and maintain the **Welcome Bihar Unified District & Block Citizen Services Data Portal** in a live production environment.

---

## 1. System Requirements

| Component | Minimum Specification | Recommended Specification |
| :--- | :--- | :--- |
| **Operating System** | Ubuntu 22.04 LTS / Debian 12 / RHEL 9 | Ubuntu 24.04 LTS |
| **Web Server** | Nginx 1.20+ or Apache 2.4+ | Nginx 1.24+ with HTTP/2 & TLS 1.3 |
| **PHP Runtime** | PHP 8.1+ | PHP 8.2 or 8.3 (PHP-FPM) |
| **Database** | MySQL 8.0+ or MariaDB 10.6+ | MySQL 8.0.35+ with InnoDB |
| **SSL/TLS** | Let's Encrypt / Commercial Certificate | Free Automated Let's Encrypt via Certbot |

### Required PHP Extensions
```bash
sudo apt update
sudo apt install -y php8.3-fpm php8.3-mysql php8.3-curl php8.3-mbstring \
                    php8.3-fileinfo php8.3-xml php8.3-zip php8.3-bcmath
```

---

## 2. Directory Permissions & File Security

The application uses the `public/` directory as its public web root. Direct HTTP access to the application core (`app/`, `cli/`, `database/`, `storage/`, `.env`) is blocked at both web server and filesystem levels.

### Recommended Production File Ownership & Permissions:
```bash
# Set deployment user ownership (e.g. www-data)
sudo chown -R www-data:www-data /var/www/welcome-bihar

# Directories standard permissions
sudo find /var/www/welcome-bihar -type d -exec chmod 755 {} \;

# Files standard permissions
sudo find /var/www/welcome-bihar -type f -exec chmod 644 {} \;

# Secure environment file (only readable by web server user)
sudo chmod 600 /var/www/welcome-bihar/.env

# Storage directories must be writable by www-data
sudo chmod -R 775 /var/www/welcome-bihar/storage/logs
sudo chmod -R 775 /var/www/welcome-bihar/storage/uploads
sudo chmod -R 770 /var/www/welcome-bihar/storage/backups
```

---

## 3. Production Environment Configuration (`.env`)

1. Copy the production template:
   ```bash
   cp .env.production.example .env
   chmod 600 .env
   ```

2. Generate a secure 64-character hexadecimal `CSRF_SECRET`:
   ```bash
   openssl rand -hex 32
   ```

3. Populate `.env` with production values:
   ```ini
   APP_NAME="Welcome Bihar"
   APP_ENV=production
   APP_DEBUG=false
   APP_URL=https://welcomebihar.example.gov.in
   APP_TIMEZONE=Asia/Kolkata
   APP_DEFAULT_LANG=en

   # Dedicated non-root MySQL user
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_NAME=welcome_bihar
   DB_USER=welcome_bihar_usr
   DB_PASS=YourStrongRandomDatabasePasswordHere
   DB_CHARSET=utf8mb4

   # Security & Session
   SESSION_LIFETIME=7200
   CSRF_SECRET=paste_the_generated_64_character_hex_secret_here

   # ALL API Live Merchant Gateway
   ALLAPI_BASE_URL=https://allapi.in
   ALLAPI_CREATE_ENDPOINT=/order/create
   ALLAPI_STATUS_ENDPOINT=/order/status
   ALLAPI_TOKEN=your_live_production_merchant_token
   ALLAPI_MODE=LIVE
   ALLAPI_REDIRECT_URL=https://welcomebihar.example.gov.in/wallet/topup/callback
   ALLAPI_TIMEOUT_SECONDS=15

   # Upload Security
   MAX_UPLOAD_SIZE_MB=5
   ALLOWED_EXTENSIONS=pdf,jpg,jpeg,png
   ```

> [!IMPORTANT]
> When `APP_ENV=production`, the application automatically:
> - Suppresses all browser error traces (`display_errors = 0`) and writes them safely to `storage/logs/app.log`.
> - Disables default demo seed accounts (`Welcome@2026`).
> - Disables sandbox QR generation and payment status bypass.
> - Enforces `Strict-Transport-Security` (HSTS) and secure cookie flags over HTTPS.

---

## 4. Database Setup & Initialization

### A. Create Dedicated Database & User
```sql
CREATE DATABASE welcome_bihar CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'welcome_bihar_usr'@'localhost' IDENTIFIED BY 'YourStrongRandomDatabasePasswordHere';
GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, DROP, INDEX, ALTER, LOCK TABLES ON welcome_bihar.* TO 'welcome_bihar_usr'@'localhost';
FLUSH PRIVILEGES;
```

### B. Import Schema & Initial Master Data
```bash
# 1. Import database tables & indexes
mysql -u welcome_bihar_usr -p welcome_bihar < database/schema.sql

# 2. Import all 38 Bihar districts and 534 blocks master data
mysql -u welcome_bihar_usr -p welcome_bihar < database/seed.sql
```

### C. Create Personalized Administrator Account
Do **not** use the default demo credentials. Create a personalized production administrator:
```bash
php cli/create_admin.php "State Administrator" "9876543210" "admin@welcomebihar.gov.in" "YourComplexAdminPassword#2026"
```
*(If the 4th password argument is omitted, the script automatically generates and prints a secure random password).*

---

## 5. Automated Database Backup System

The codebase includes an automated, self-contained backup utility: `cli/db_backup.php`.

### A. Manual Backup Verification
```bash
# Run a dry-run check
php cli/db_backup.php --dry-run

# Run full backup (outputs SQL dump in storage/backups/ with SHA256 checksum)
php cli/db_backup.php
```

### B. Setup Automated Daily Cron Job
Run backups every night at 02:00 AM with a 14-day retention cycle:
```bash
sudo crontab -e
```
Add the line:
```cron
0 2 * * * /usr/bin/php /var/www/welcome-bihar/cli/db_backup.php --keep=14 >> /var/www/welcome-bihar/storage/logs/backup.log 2>&1
```

---

## 6. Web Server & HTTPS Deployment

### Option A: Nginx (Recommended)
1. Copy the provided Nginx configuration:
   ```bash
   sudo cp nginx.conf.example /etc/nginx/sites-available/welcomebihar
   sudo nano /etc/nginx/sites-available/welcomebihar # Replace domain name & PHP-FPM socket path
   sudo ln -s /etc/nginx/sites-available/welcomebihar /etc/nginx/sites-enabled/
   ```
2. Obtain SSL Certificate with Certbot:
   ```bash
   sudo certbot --nginx -d welcomebihar.example.gov.in
   ```
3. Test and reload Nginx:
   ```bash
   sudo nginx -t
   sudo systemctl reload nginx
   ```

### Option B: Apache
1. Ensure `mod_rewrite`, `mod_headers`, and `mod_ssl` are enabled:
   ```bash
   sudo a2enmod rewrite headers ssl
   ```
2. Set `DocumentRoot` in your VirtualHost configuration to `/var/www/welcome-bihar/public`.
3. Verify that `AllowOverride All` is set on the DocumentRoot directory.

---

## 7. Pre-Launch Verification Suite

Before accepting public traffic, execute the non-destructive automated audit tools:

### A. Run Production Audit Check
```bash
php cli/production_check.php
```
Verify that all runtime extensions, storage permissions, security policies, and environment flags return `[PASS]`.

### B. Run Full Core Logic Test Suite
```bash
php cli/test_suite.php
```
Ensure all 46 core security and unit tests pass with 0 failures.

---

## 8. Webhook Configuration with ALL API

In your ALL API Merchant Dashboard:
1. Set the **Callback / Webhook URL** to:
   `https://welcomebihar.example.gov.in/wallet/topup/webhook`
2. Set the **Redirect URL** to:
   `https://welcomebihar.example.gov.in/wallet/topup/callback`
3. Copy your secret merchant token into `.env` as `ALLAPI_TOKEN`.
4. Ensure the webhook signs payloads via HMAC-SHA256.

---

## 9. Rollback & Disaster Recovery Procedure

If a deployment needs to be rolled back to a previous database state:
1. Put web server into temporary maintenance mode (e.g. return 503).
2. Locate the desired backup file in `storage/backups/`.
3. Restore database:
   ```bash
   mysql -u welcome_bihar_usr -p welcome_bihar < storage/backups/backup_welcome_bihar_YYYYMMDD_HHMMSS.sql
   ```
4. Clear opcache / restart PHP-FPM:
   ```bash
   sudo systemctl restart php8.3-fpm
   ```

---

## 10. WhatsApp Notification Dispatcher Scheduler (WBAPI Outbox)

The platform uses an asynchronous, resilient database outbox (`whatsapp_notification_outbox`) to deliver WhatsApp notifications to Block Operators without delaying user HTTP responses.

To process scheduled retries, pending outbox records, and stuck worker recovery in production, run the dispatcher worker every minute.

### Worker Command
```bash
php cli/dispatch_whatsapp.php --limit=50
```

### A. Linux (Cron Setup)
Run as the web-server user (e.g., `www-data`):
```bash
sudo crontab -u www-data -e
```
Add the entry to run every minute:
```cron
* * * * * /usr/bin/php /var/www/welcome-bihar/cli/dispatch_whatsapp.php --limit=50 >> /var/www/welcome-bihar/storage/logs/whatsapp_cron.log 2>&1
```

### B. Windows Server (Task Scheduler)
You can register a recurring 1-minute Windows Scheduled Task using PowerShell / Command Prompt as Administrator:

```powershell
schtasks /Create /SC MINUTE /MO 1 /TN "WelcomeBihar_WhatsAppDispatcher" /TR "php C:\inetpub\wwwroot\welcome-bihar\cli\dispatch_whatsapp.php --limit=50" /RU "SYSTEM" /F
```

Or via PowerShell with explicit working directory:
```powershell
$action = New-ScheduledTaskAction -Execute "php.exe" -Argument "cli/dispatch_whatsapp.php --limit=50" -WorkingDirectory "C:\inetpub\wwwroot\welcome-bihar"
$trigger = New-ScheduledTaskTrigger -Once -At (Get-Date) -RepetitionInterval (New-TimeSpan -Minutes 1)
Register-ScheduledTask -TaskName "WelcomeBihar_WhatsAppDispatcher" -Action $action -Trigger $trigger -User "SYSTEM"
```

### Concurrency & Duplicate Protection Architecture
- **Process Mutex (`flock`)**: `cli/dispatch_whatsapp.php` acquires a non-blocking process file lock on `storage/locks/whatsapp_dispatcher.lock`. If a prior dispatch run is still in progress, subsequent runs exit gracefully without overlapping.
- **Atomic Row-Level Token Claim**: Outbox records are claimed atomically via:
  `UPDATE whatsapp_notification_outbox SET status = 'PROCESSING', lock_token = :token, locked_at = NOW() WHERE id = :id AND status = 'PENDING'`
  Only the worker holding the matching unique `lock_token` is authorized to transmit via `sendOne()`.
- **Stuck Worker Recovery**: Workers automatically release rows stuck in `PROCESSING` for > 10 minutes back to `PENDING` with cleared lock tokens so no notification is permanently stranded.
- **Retry Backoff Schedule**: Immediate attempt 1, +1 minute attempt 2, +5 minutes attempt 3, +15 minutes attempt 4, then permanently `FAILED`. Respects HTTP 429 `Retry-After` headers.

