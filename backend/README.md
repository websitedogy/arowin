# Arowin backend

The PHP API stores employee accounts in MySQL. Team Leaders and Telecallers can sign in with their six-digit Employee ID and password; Sub Editors sign in with their configured email and password. The API routes each role to its own dashboard. Passwords are stored with PHP's `password_hash`, and API access uses a server-side session.

## Requirements

- XAMPP PHP 8.1 or later with `pdo_mysql` and `session` enabled
- MySQL
- Node.js for the frontend

## Backend structure

API features live in their own module folders. Shared startup, database configuration, and database setup remain in the API root; feature-specific controllers and services belong under `api\modules\<feature>`.

```text
api\
  modules\
    auth\
    credentials\
    dashboard\
    employees\
    team-assignments\
  config\
  bootstrap.php
  Database.php
  index.php
  setup.php
```

## Configure and initialize the database

Start MySQL from the XAMPP Control Panel. Set the connection settings and initial Sub Editor in the same PowerShell window that will run the backend. Use XAMPP's PHP executable (`C:\xampp\php\php.exe`) because the other PHP install on this machine may not have `pdo_mysql` enabled. Store the Gmail App Password in `SMTP_PASSWORD` in the backend process environment. The backend reads `AROWIN_CREDENTIAL_ENCRYPTION_KEY` from its environment or the ignored `backend\.env` file. Generate a new encryption key once and keep it backed up; do not rotate it while unused credential links are pending.

```powershell
$env:DB_HOST = "127.0.0.1"
$env:DB_PORT = "3306"
$env:DB_NAME = "arowin_db"
$env:DB_USER = "root"
$env:DB_PASSWORD = ""
$env:AROWIN_INITIAL_ADMIN_EMAIL = "admin@arowin.com"
$env:AROWIN_INITIAL_ADMIN_PASSWORD = "choose-a-password"
$env:AROWIN_INITIAL_ADMIN_NAME = "Sub Editor Admin"
$env:SMTP_HOST = "smtp.gmail.com"
$env:SMTP_PORT = "587"
$env:SMTP_USERNAME = "arowintradin@gmail.com"
$securePassword = Read-Host "Gmail App Password" -AsSecureString
$passwordPointer = [Runtime.InteropServices.Marshal]::SecureStringToBSTR($securePassword)
try {
    $env:SMTP_PASSWORD = [Runtime.InteropServices.Marshal]::PtrToStringBSTR($passwordPointer)
} finally {
    [Runtime.InteropServices.Marshal]::ZeroFreeBSTR($passwordPointer)
}
$env:SMTP_ENCRYPTION = "tls"
$env:AROWIN_PUBLIC_FRONTEND_URL = "https://your-public-arowin-site.example"
C:\xampp\php\php.exe backend\api\setup.php
```

Generate a key once and store it in `backend\.env` (do not commit the file):

```powershell
$key = C:\xampp\php\php.exe -r "echo base64_encode(random_bytes(32));"
"AROWIN_CREDENTIAL_ENCRYPTION_KEY=$key" | Set-Content backend\.env
```

Back up this key securely. Keep the same key across API restarts; it is required to decrypt credentials waiting for download.

The setup script applies `db\schema.sql`, adds missing employee profile fields to an existing schema without deleting data, and creates the initial Sub Editor account. Joining dates are stored with salary records and are set in Salary Setup. Existing `users.joining_date` columns are left untouched for data safety, but are no longer read or written. Set the same `DB_*` values in the shell that runs the API.

To apply additive schema updates to an already initialized database without reseeding or changing accounts, run `C:\xampp\php\php.exe backend\api\setup.php --schema-only` from the repository root. This mode requires an existing active Sub Editor account.

Do not copy the placeholder above literally or generate a replacement on each run. After setup, start the API from that same PowerShell window so its environment settings are available:

```powershell
composer install --working-dir=backend --no-interaction
C:\xampp\php\php.exe -S 127.0.0.1:8000 -t backend\api backend\api\index.php
```

For Gmail SMTP, enable 2-Step Verification on the sender Google account and create an **App Password** in Google Account security settings. Put that app password in `SMTP_PASSWORD`; do not use the normal Google password, commit the app password, or send it in chat. The sender is `AROWIN <arowintradin@gmail.com>`. Keep the encryption key stable because it protects undelivered passwords held for the one-time download flow. `AROWIN_PUBLIC_FRONTEND_URL` must be the recipient-reachable website URL; `localhost` only works on the same machine and is not suitable for real employee email.

## Start the application

From the repository root, start the API:

```powershell
C:\xampp\php\php.exe -S 127.0.0.1:8000 -t backend\api backend\api\index.php
```

In another terminal:

```powershell
npm --prefix frontend run dev
```

The frontend defaults to port `8000` on the same host. Override it with `VITE_API_URL` if the API runs elsewhere. To permit a different frontend origin, set `AROWIN_FRONTEND_ORIGINS` to a comma-separated allowlist.

## Account flow

- Sign in as the provisioned Sub Editor, then create employee accounts from the dashboard.
- Each created account and its activity log are saved together in MySQL.
- An email from `AROWIN <arowintradin@gmail.com>` contains a secure credentials-download link and an **Install AROWIN Web App** link for the Team Leader or Telecaller dashboard, not the password itself. The install link opens the app's install instructions; supported browsers can install it to the device's home screen or apps list. The public frontend must use HTTPS for installation (localhost is allowed for development). The credential-download link expires after 24 hours and reveals the account email/password once. If SMTP fails, the account remains created and the Sub Editor dashboard offers **Retry email**.
- The password awaiting download is encrypted at rest; the link token is stored as a hash. After download, the encrypted password is erased and the link cannot be reused.
- Sign in as a Sub Editor with the configured email and password. Team Leaders and Telecallers sign in using their six-digit Employee ID and password, and receive separate role-specific dashboard workspaces. Only Sub Editors can create accounts.
- In **Target Setup → Work Assigning to TL**, Sub Editors can assign each active Telecaller to one Team Leader. Assigned Telecallers and assignment activity appear in that Team Leader's dashboard. Team assignment data is stored in MySQL.
- Employee profiles contain a name, unique email, role, mobile number, date of birth, address, active status, and a password hash. Joining date is configured alongside salary in Salary Setup; employees without one display **Not recorded**.
- Every account has a unique six-digit `employee_id`. The setup script assigns IDs to existing users once and enforces a unique database index; IDs are returned in employee, login, and credential-download responses.

API routes:

- `POST /api/auth/login`
- `POST /api/auth/logout`
- `GET /api/auth/me`
- `GET /api/employees` (Sub Editor session)
- `POST /api/employees` (Sub Editor session)
- `GET /api/activity/recent` (Sub Editor session)
- `GET /api/credentials/download/{token}` (one-time link check)
- `POST /api/credentials/download/{token}` (consume link and retrieve credentials)
- `POST /api/employees/{id}/credentials/resend` (Sub Editor session)
- `GET /api/budget/salaries` (Sub Editor session)
- `POST /api/budget/salaries/{employeeId}` (Sub Editor session)
- `GET /api/attendance/office-location` (Sub Editor session)
- `GET /api/attendance/holidays` (Sub Editor session)
- `POST /api/attendance/holidays` (Sub Editor session)
- `DELETE /api/attendance/holidays/{date}` (Sub Editor session)
- `GET /api/attendance/me` (Team Leader or Telecaller session)
- `GET /api/attendance/history?month=YYYY-MM` (Team Leader or Telecaller session)
- `POST /api/attendance/location-check` (Team Leader or Telecaller session)
- `POST /api/attendance/check-in` (Team Leader or Telecaller session)
- `POST /api/attendance/check-out` (Team Leader or Telecaller session)

The office pin is preconfigured for the Khammam office. Attendance is accepted only within 200 meters; check-in and check-out both require live browser location (reported accuracy up to 200 meters) and a selfie. Check-in is available from 12:00 AM until 10:30 AM IST; check-ins from 9:46 AM until the window closes are late (₹100 deduction). Check-out opens at 5:30 PM IST. Sundays and Sub Editor-configured festival holidays are paid days and do not require attendance; on working days, earned salary is credited only after both check-in and check-out. Employee dashboards show a monthly calendar and daily attendance history. Selfie files are stored under `backend\storage\attendance-selfies`, outside the API document root. Browser camera and location access require HTTPS or localhost. Daily salary is the monthly salary divided by the month's actual calendar days. Employees must have a salary setup before they can check in.
- `GET /api/dashboard/summary` (authenticated session)
- `GET /api/team-assignments` (Sub Editor session)
- `GET /api/team-assignments/{teamLeaderId}/available-telecallers` (Sub Editor session)
- `GET /api/team-assignments/history` (Sub Editor session)
- `GET /api/team-assignments/{teamLeaderId}/telecallers` (Sub Editor session)
- `POST /api/team-assignments` (Sub Editor session)
- `GET /api/team-leader/team` (Team Leader session)

Install PHP dependencies before starting the API:

```powershell
composer install --working-dir=backend --no-interaction
```
