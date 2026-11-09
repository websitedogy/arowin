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
    daily-activities\
    dashboard\
    employees\
    leave\
    sales\
    targets\
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

The first Employee KYC submission creates a separate 32-byte key at `backend\storage\employee-kyc\encryption.key`. This file is private and ignored by Git. Back it up securely with the database; losing it makes saved KYC bank and identity numbers unreadable.

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
- Team Leaders and Telecallers can add or replace their profile photo from **Profile**. JPEG, PNG, and WebP files up to 5 MB are validated and saved privately for that employee; the photo also appears in the dashboard header.
- Team Leaders and Telecallers can submit Employee KYC in four steps: prefilled profile details, bank details, Aadhaar/PAN numbers with JPEG/PNG/PDF document uploads (up to 5 MB each), and read-only office details from the employee and office records. KYC values are encrypted at rest; documents are kept outside the public API directory and the API only returns the signed-in employee's own KYC. KYC profile edits do not change login or employee-account details.
- Team Leaders and Telecallers can open **Support** to raise and track categorized tickets, set a priority, and continue the conversation with the Sub Editor. Sub Editors use **Support Tickets** to triage every request, reply, and update ticket status. Employees can only view their own tickets; replying to a resolved or closed ticket reopens it.
- In **Target Setup → Team Assignment**, Sub Editors can assign each active Telecaller to one Team Leader, see the remaining unassigned Telecaller count beside **Assign**, and view each leader’s team and assigned-member count. The available count updates after an assignment. Assigned Telecallers and assignment activity appear in that Team Leader's dashboard. Team assignment data is stored in MySQL.
- In **Target Setup → Sales Target Setup**, Sub Editors can save a monthly USDT target for each active Team Leader. Saving or updating a target requires confirming the Sub Editor's current password; the API verifies it against the stored password hash. Targets are unique per leader and month, appear on the Team Leader dashboard for the active month, and each leader's **View** history shows the saved amount and latest update date/time for the last 12 months.
- The Team Leader dashboard and Telecaller sales trend show approved package sales only. Team Leader totals aggregate approved sales from currently assigned Telecallers and compare them with that leader's selected monthly target; Telecallers can review their personal approved sales without an individual target. Daily, weekly, and monthly charts show progress bands (red below 33%, yellow from 33% through 66%, green above 66%) and a 50% break-even marker.
- The Sub Editor **Total Sales**, **This Week's Sales**, and **Last Month's Sales** pages summarize approved unlock package sales in USDT from Telecallers and Team Leaders. Total Sales includes all approved entries through today; This Week uses Monday through today in Asia/Kolkata; Last Month uses the previous calendar month. Each page shows the overall total and a per-employee breakdown. Pending requests are shown separately in **Total Sales** and are not included in approved sales totals.
- Sub Editors configure attendance on-time bonuses, daily activity amounts by platform and a daily activity bonus limit, and the USDT incentive for each supported unlock package in **Settings**. Attendance bonuses apply to check-ins by 9:30 AM. Each approved daily activity earns its saved platform amount once, up to the configured per-employee daily limit; updated rates affect future approvals. Package incentives are recorded on sales when approved. Once the Arowin Global lookup is connected, Telecallers and Team Leaders can find a customer by ID, see the verified customer name and activated package, and submit that purchase for Sub Editor review. Approval records the configured incentive against the employee's sale; it appears in the submitter's **My Incentives** page and approved team sales appear in **Our Team Achievers** as the employee's name and an achievement only. Customer IDs, customer names, and package details are not shown to teammates. Until the Arowin Global API/data source is configured, lookup and sale submission are unavailable and manually selected/unverified packages cannot be submitted.
- **My Unlock Sales → Lookup Customer** is available for Telecallers and Team Leaders. It will use the Arowin Global customer data source after that API is configured. Do not enter or credit package sales manually before the lookup is enabled.
- Team Leader assignment notifications appear in the notification menu, with a month-filtered 12-month activity history on the dashboard. History includes employee ID, employee name, and assignment date.
- Team member joining dates use the configured salary record, falling back to a legacy `users.joining_date` column when present.
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
- `GET /api/budget` (Sub Editor session; company budget, bank name, account holder, masked account number, IFSC code, and category allocations)
- `POST /api/budget/company` (Sub Editor session; JSON body includes positive `totalBudget`, `bankName`, `accountHolderName`, `accountNumber` on initial setup, and `ifscCode`; when editing, an empty account number retains the saved number)
- `POST /api/budget/allocations` (Sub Editor session; JSON body assigns or updates one category at a time, for example `{"allocations":{"office":"25000"}}`; total assigned category budgets cannot exceed the Company Budget, and setting an amount to `0` removes that category assignment)
- `GET /api/attendance/office-location` (Sub Editor session)
- `GET /api/attendance/holidays` (Sub Editor session)
- `POST /api/attendance/holidays` (Sub Editor session)
- `DELETE /api/attendance/holidays/{date}` (Sub Editor session)
- `GET /api/attendance/me` (Team Leader or Telecaller session)
- `GET /api/attendance/history?month=YYYY-MM` (Team Leader or Telecaller session)
- `POST /api/attendance/location-check` (Team Leader or Telecaller session)
- `POST /api/attendance/check-in` (Team Leader or Telecaller session)
- `POST /api/attendance/check-out` (Team Leader or Telecaller session)
- `GET /api/leave-requests` (own requests for Team Leaders and Telecallers; all requests for Sub Editors)
- `POST /api/leave-requests` (Team Leader or Telecaller session; JSON body includes `leaveType` (`casual`, `sick`, `emergency`, or `personal`), `startDate`, `endDate`, and `reason`)
- `POST /api/leave-requests/{id}/decision` (Sub Editor session; JSON body includes `decision` set to `approved` or `rejected`)
- `GET /api/daily-activities` (own submissions for Team Leaders and Telecallers; all submissions for Sub Editors)
- `GET /api/daily-activities/settings` (Sub Editor session; platform reward rates and per-employee daily bonus maximum)
- `POST /api/daily-activities/settings` (Sub Editor session; save platform rates and daily maximum)
- `GET /api/leads` (Telecaller session; own lead entries)
- `POST /api/leads` (Telecaller session; create a lead with customer name, mobile, district, and optional note)
- `POST /api/daily-activities` (Team Leader or Telecaller multipart form with `platform` and either 1–5 `screenshots[]` image files or 1–2 YouTube `videos[]` files)
- `GET /api/daily-activities/{id}/screenshots/{screenshotId}` (private screenshot for its submitter or a Sub Editor)
- `GET /api/daily-activities/{id}/videos/{videoId}` (private YouTube video for its submitter or a Sub Editor)
- `POST /api/daily-activities/{id}/decision` (Sub Editor session; JSON body includes `decision` (`approve` or `reject`) and a required `reason` when rejecting)
- `POST /api/daily-activities/{id}/verify` (legacy approval endpoint; Sub Editor session)
- `GET /api/attendance/settings` (Sub Editor session; on-time attendance bonus amount)
- `POST /api/attendance/settings` (Sub Editor session; save on-time attendance bonus amount)

The office pin is preconfigured for the Khammam office. Attendance is accepted only within 200 meters; check-in and check-out both require live browser location (reported accuracy up to 200 meters) and a selfie. Check-in is available from 12:00 AM until 10:30 AM IST; check-ins from 9:46 AM until the window closes are late (₹100 deduction). Check-out opens at 5:30 PM IST. Sundays and Sub Editor-configured festival holidays are paid days and do not require attendance; on working days, earned salary is credited only after both check-in and check-out. Employee dashboards show a monthly calendar and daily attendance history. Selfie files are stored under `backend\storage\attendance-selfies`, outside the API document root. Browser camera and location access require HTTPS or localhost. Daily salary is the monthly salary divided by the month's actual calendar days. Employees must have a salary setup before they can check in.

Team Leaders and Telecallers can submit multiple daily activities. Instagram, Facebook, and WhatsApp Status submissions accept up to five JPEG, PNG, or WebP screenshots (4 MB each); screenshots are proof for one activity submission. The Sub Editor configures the amount for each approved platform activity and the daily maximum in **Settings**. Verified activity bonuses are included in attendance salary cut-off calculations after Sub Editor review. Existing verified social-post bonus history remains unchanged.

YouTube submissions accept one or two MP4 or WebM Shorts videos per activity (50 MB each). Sub Editors can play private video uploads and approve or reject the activity; rejection requires a reason that is shown to the employee. The weekly target is two approved videos. YouTube and other platform rates are configurable in **Settings**. Screenshot and video files are stored privately under `backend\storage\daily-activity-screenshots` and `backend\storage\daily-activity-videos`, outside the API document root.

Approved leave is unpaid: each elapsed approved working day without a completed attendance record is included in the employee's leave cut-off at the daily salary rate. Pending and rejected requests do not receive leave treatment; other unattended workdays remain unpaid as usual. Sundays and configured holidays remain paid. Approved leave appears in the attendance calendar and prevents a new check-in for that day.
- `GET /api/dashboard/summary` (authenticated session)
- `GET /api/team-assignments` (Sub Editor session)
- `GET /api/team-assignments/{teamLeaderId}/available-telecallers` (Sub Editor session)
- `GET /api/team-assignments/history` (Sub Editor session)
- `GET /api/team-assignments/{teamLeaderId}/telecallers` (Sub Editor session)
- `POST /api/team-assignments` (Sub Editor session)
- `GET /api/team-leader/team` (Team Leader session)
- `GET /api/sales-targets?month=YYYY-MM` (Sub Editor session)
- `POST /api/sales-targets/{teamLeaderId}` (Sub Editor session; JSON body includes `month`, `targetAmount`, and the confirming Sub Editor `password`)
- `GET /api/sales/performance?month=YYYY-MM` (Team Leader or Telecaller session)
- `GET /api/sales/summary?period=total|this_week|last_month` (Sub Editor session; total and per-employee approved sales)
- `GET /api/sales/packages` (Sub Editor, Team Leader, or Telecaller session; supported package amounts and configured incentives)
- `POST /api/sales/packages/incentives` (Sub Editor session; configure USDT incentives for all supported packages)
- `POST /api/sales/unlocks` (Team Leader or Telecaller session; currently blocked until a verified Arowin Global customer package lookup is configured)
- `GET /api/sales/unlocks/me` (Team Leader or Telecaller session; own submitted sales and earned incentive total)
- `GET /api/sales/unlocks?status=pending|approved|rejected|all` (Sub Editor session; sale requests and status totals)
- `POST /api/sales/unlocks/{saleId}/review` (Sub Editor session; approve or reject a pending sale, with a reason required for rejection)
- `GET /api/sales/incentives/me` (Team Leader or Telecaller session; approved personal incentives)
- `GET /api/sales/team-achievements` (Team Leader or Telecaller session; assigned team's approved sale achievements, employee names only)
- `GET /api/sales/incentives/earned` (Sub Editor session; approved incentive totals and history)
- `POST /api/sales/daily` (Telecaller session; saves or updates a date's total USDT sales)

Install PHP dependencies before starting the API:

```powershell
composer install --working-dir=backend --no-interaction
```
