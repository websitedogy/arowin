# Complete Backend Test Inventory Audit

This document is an evidence-based audit of the backend API under `backend/api` and the existing PHPUnit suite under `backend/tests`.

Scope restrictions observed for this audit:
- No production code was modified.
- No new testing batch was started.
- No Layer 2 work was initiated.
- This is an inventory and gap assessment only.

## 1. Backend discovery summary

### Project architecture discovered
- Backend runtime: PHP 8.1+
- Database access: PDO/MySQL via `Database::connect()`
- API entry point: `backend/api/index.php`
- Session/auth routing: `backend/api/index.php` and `backend/api/RouteAuthorization.php`
- Feature modules: `backend/api/modules/*`
- Shared support utilities: `backend/api/*.php`, `backend/api/Shared/*.php`

### Inventory totals
- PHP files discovered under `backend/api`: 47
- Classes discovered: 32
- Method/function declarations identified: 210
- Feature modules under `backend/api/modules`: 13
- Controllers: 14
- Concrete business service: 1 (`CredentialDeliveryService`)
- Repository/adapter-related classes: 10
- Shared/root utilities/helpers: 11
- Existing PHPUnit test files: 13
- Existing PHPUnit tests executed in current evidence: 52
- Existing PHPUnit assertions executed in current evidence: 191

## 2. Full module inventory

| Module | Key production classes / files | Notes |
|---|---|---|
| `attendance` | `AttendanceController` | Check-in/check-out, attendance history, location verification, attendance assertions |
| `auth` | `AuthController` | Login/logout/current-user and profile image handling |
| `budget` | `BudgetController` | Budget summary and salary/budget entry flows |
| `credentials` | `CredentialDeliveryService`, `PdoCredentialRepository`, `PhpMailerAdapter`, `AesGcmEncryptionAdapter` | Link generation, email delivery, token redemption, encryption |
| `daily-activities` | `DailyActivityController` | Daily activity recording, uploads, approval/verification |
| `dashboard` | `DashboardController` | Dashboard summary and aggregation |
| `employees` | `EmployeeController`, `EmployeeKycController`, `PdoKycRepository`, `LocalDocumentStorageAdapter`, `EmployeeIdGenerator` | Employee lifecycle, KYC and identity handling |
| `leads` | `LeadController` | Lead listing and creation |
| `leave` | `LeaveController` | Leave apply/decide and validation |
| `sales` | `SalesController`, `UnlockOtpController`, `PdoUserRepository`, `PhpSessionOtpChallengeStore`, `PhpMailerOtpAdapter` | Sales performance, unlock flow and OTP verification |
| `support` | `SupportController` | Support ticket lists and replies |
| `targets` | `SalesTargetController` | Sales target listing, saving, and historical review |
| `team-assignments` | `TeamAssignmentController` | Team leader/telecaller assignment logic |

### Shared/root support and utility layer
- `Database` — PDO connection bootstrap
- `EmployeeRoles` — role normalization and labels
- `RouteAuthorization` — authenticated-role checks
- `AppTime` — timezone-aware time access
- `ValueFormatter` — sales format and month normalization
- `OtpGenerator` — OTP creation helper
- `SystemClock` — production clock abstraction
- `EnvironmentFrontendUrlProvider` — environment-based front-end URL
- `FrontendUrlProvider` — interface contract for URLs
- `EncryptionPort` — encryption contract
- `ClockInterface` — clock abstraction
- `bootstrap.php` — config/logger/JSON helper utilities
- `index.php` — route dispatch and access control

## 3. Existing test mapping by production area

### Test files currently present
- `backend/tests/Modules/Attendance/AttendanceControllerTest.php`
- `backend/tests/Modules/Auth/AuthControllerTest.php`
- `backend/tests/Modules/Credentials/CredentialDeliveryServiceTest.php`
- `backend/tests/Modules/Employees/EmployeeControllerTest.php`
- `backend/tests/Modules/Employees/EmployeeIdGeneratorTest.php`
- `backend/tests/Modules/Employees/EmployeeKycControllerTest.php`
- `backend/tests/Modules/Leave/LeaveControllerTest.php`
- `backend/tests/Modules/Sales/SalesControllerTest.php`
- `backend/tests/Modules/Sales/UnlockOtpControllerTest.php`
- `backend/tests/Modules/TeamAssignments/TeamAssignmentControllerTest.php`
- `backend/tests/RouteAuthorizationTest.php`
- `backend/tests/Shared/AppTimeTest.php`
- `backend/tests/Shared/ValueFormatterTest.php`

### Component classification summary

| Production component | Test status | Evidence |
|---|---|---|
| `AttendanceController` | FULLY TESTED | `AttendanceControllerTest.php` covers check-in, duplicate check-in, history, location validation |
| `AuthController` | FULLY TESTED | `AuthControllerTest.php` covers login, wrong-password rejection, current-user normalization |
| `CredentialDeliveryService` | FULLY TESTED | `CredentialDeliveryServiceTest.php` covers prepare/inspect/redeem/resend flows |
| `EmployeeController` | FULLY TESTED | `EmployeeControllerTest.php` covers creation, validation, duplicate email |
| `EmployeeIdGenerator` | FULLY TESTED | `EmployeeIdGeneratorTest.php` covers six-digit generation |
| `EmployeeKycController` | FULLY TESTED | `EmployeeKycControllerTest.php` covers valid payloads and invalid documents |
| `LeaveController` | FULLY TESTED | `LeaveControllerTest.php` covers leave application, overlap rejection, approval |
| `SalesController` | FULLY TESTED | `SalesControllerTest.php` covers performance role checks and summary logic |
| `TeamAssignmentController` | FULLY TESTED | `TeamAssignmentControllerTest.php` covers assignment creation and leader validation |
| `UnlockOtpController` | FULLY TESTED | `UnlockOtpControllerTest.php` covers OTP send + verify + expiry/invalid cases |
| `RouteAuthorization` | FULLY TESTED | `RouteAuthorizationTest.php` covers auth and role restrictions |
| `AppTime` | FULLY TESTED | `AppTimeTest.php` verifies timezone handling |
| `ValueFormatter` | FULLY TESTED | `ValueFormatterTest.php` covers sales validation and month normalization |
| `BudgetController` | NOT TESTED | No direct PHPUnit file; controller logic is currently untested |
| `DailyActivityController` | NOT TESTED | No matching PHPUnit file; upload and verification flows are untested |
| `DashboardController` | NOT TESTED | No matching PHPUnit file |
| `LeadController` | NOT TESTED | No matching PHPUnit file |
| `SupportController` | NOT TESTED | No matching PHPUnit file |
| `SalesTargetController` | NOT TESTED | No matching PHPUnit file |
| `Database` | BLOCKED | Production DB coupling requires isolation for reliable unit testing |
| `PdoCredentialRepository` | BLOCKED | Direct PDO access is not unit-isolated in the current test setup |
| `PdoKycRepository` | BLOCKED | Direct persistence logic is difficult to isolate without repository abstraction in tests |
| `PdoUserRepository` | BLOCKED | Direct lookup logic remains DB-coupled and not isolated in a pure unit-test setup |
| `LocalDocumentStorageAdapter` | PARTIALLY TESTED | Indirectly exercised by KYC tests but not directly unit-tested |
| `AesGcmEncryptionAdapter` | PARTIALLY TESTED | Indirectly exercised by KYC and credential flows, but not covered as a standalone unit |
| `PhpMailerAdapter` / `PhpMailerOtpAdapter` | PARTIALLY TESTED | Real integrations are abstracted in tests; direct SMTP behavior is not unit-isolated |
| `EmployeeRoles` | PARTIALLY TESTED | Used indirectly in authorization logic and role normalization |

## 4. Business logic risk assessment

### High-risk categories identified
- Financial calculations: `BudgetController`, sales totals, target totals, salary summary
- Employee operations: `EmployeeController`, `EmployeeIdGenerator`, employee profile and KYC creation
- Attendance calculations: `AttendanceController` working-day logic, leave assertion, office-distance validation
- Leave calculations: `LeaveController` overlap and approval rules
- KYC processing: `EmployeeKycController` file validation, encryption, storage and retrieval
- Credential management: `CredentialDeliveryService` hashing, expiry, rotation, redemption
- OTP operations: `UnlockOtpController` expiry, resend throttling, invalid-code handling, lockout logic
- Sales operations: `SalesController`, `SalesTargetController`
- Team management: `TeamAssignmentController`
- Budget calculations: `BudgetController`
- File handling: `DailyActivityController`, `EmployeeKycController`, `AuthController` profile-image 
- Authentication and authorization: `AuthController`, `RouteAuthorization`, `index.php`
- Database transactions: credential issuance and KYC save paths

### Highest-priority business logic by severity
1. Credential issuance and redemption (`CredentialDeliveryService`)
2. KYC validation and document storage (`EmployeeKycController`)
3. OTP verification and lockout logic (`UnlockOtpController`)
4. Authorization and session restrictions (`RouteAuthorization` and `index.php`)
5. Attendance validation and payroll-relevant calculations (`AttendanceController`)
6. Leave overlap and approval logic (`LeaveController`)
7. Sales calculations and role restrictions (`SalesController`)
8. Team assignment integrity (`TeamAssignmentController`)
9. Budget and salary accounting (`BudgetController`)
10. Daily-activity and support transactional flows (`DailyActivityController`, `SupportController`)

## 5. Dependency analysis and gap profile

### Direct PDO/MySQL coupling
Observed across controller and repository classes:
- `Database::connect()` creates PDO connections
- `AuthController` uses direct DB operations
- `EmployeeController` and `EmployeeKycController` perform persistence directly or via PDO repositories
- `AttendanceController`, `LeaveController`, `SalesController`, `SalesTargetController`, and `TeamAssignmentController` depend on the DB layer
- `CredentialDeliveryService` uses `PdoCredentialRepository`
- `UnlockOtpController` uses `PdoUserRepository`

Risk: These components are capable of pure business logic, but their tests currently depend on the data layer or logic that is not fully isolated.

### SMTP / mail delivery dependencies
- `PhpMailerAdapter` sends credential email links
- `PhpMailerOtpAdapter` sends OTP codes
- `CredentialDeliveryService` and `UnlockOtpController` call these mailers indirectly

Risk: SMTP behavior should be isolated behind ports/adapters to avoid production configuration dependence in unit tests.

### Session and authorization dependencies
- `index.php` uses session state and headers
- `RouteAuthorization` enforces role-gated access
- `UnlockOtpController` stores verification state in PHP session via `PhpSessionOtpChallengeStore`

Risk: Session behavior is testable only when abstracted or mocked cleanly.

### File-system dependencies
- `LocalDocumentStorageAdapter` stores uploaded documents
- `AuthController` manages profile image storage
- `EmployeeKycController` validates document uploads and stores encrypted KYC documents

Risk: File paths, MIME validation, size checks, and cleanup are business-sensitive and should be isolated from production storage for unit tests.

### Encryption and key management dependencies
- `AesGcmEncryptionAdapter`
- `EncryptionPort`
- `CredentialDeliveryService` encrypts credential secrets
- `EmployeeKycController` encrypts Aadhaar/PAN and bank account data

Risk: Data privacy and format compatibility are important; tests should use explicit, non-production keys and isolated adapters.

### Environment-variable and global-state dependencies
- `index.php` reads environment variables for CORS and session configuration
- `bootstrap.php` loads config from environment values
- `EnvironmentFrontendUrlProvider` reads runtime environment configuration

Risk: Global state makes pure unit-isolation harder; direct tests must mock or isolate environment access.

## 6. Coverage analysis

### Existing production files without direct PHPUnit coverage
The following major production classes currently have no direct test file allocation:
- `BudgetController`
- `DailyActivityController`
- `DashboardController`
- `LeadController`
- `SalesTargetController`
- `SupportController`
- `Database` (directly coupled but not unit-isolated)
- `EmployeeRoles`
- `PdoCredentialRepository`
- `PdoKycRepository`
- `PdoUserRepository`
- `LocalDocumentStorageAdapter`
- `PhpMailerAdapter`
- `PhpMailerOtpAdapter`
- `AesGcmEncryptionAdapter`

### Functions without direct tests
Major method groups without direct coverage include:
- `BudgetController::summary`, `saveCompanyBudget`, `saveAllocations`, `listSalaries`, `saveSalary`, `validBudgetAmount`, `amountToCents`, `validRequiredText`, `maskAccountNumber`
- `DailyActivityController::listActivities`, `submit`, `verify`, `decide`, `screenshot`, `video`, `normalizeUploads`, `safeOriginalName`, `uploadErrorMessage`
- `DashboardController::summary`
- `LeadController::listForTelecaller`, `create`
- `SalesTargetController::listMonthlyTargets`, `saveMonthlyTarget`, `verifySubEditorPassword`, `listHistory`
- `SupportController::listTickets`, `getTicket`, `createTicket`, `reply`, `text`
- `PdoCredentialRepository::insertRecord`, `findUnusedByUserId`, `findValidByTokenHash`, `rotateLatestForUser`, `markEmailSent`, `redeemByTokenHash`
- `PdoKycRepository::findByUserId`, `findExistingDocuments`, `saveSubmission`
- `PdoUserRepository::findSalesEmployeeById`
- `LocalDocumentStorageAdapter::store`, `delete`
- `AesGcmEncryptionAdapter::encrypt`, `decrypt`

### Untested branches and gaps
- Negative path coverage for budget and support functions is absent
- Download/credential expiry edge cases are partially covered but not exhaustive at repository level
- File upload/size/dimension validation branches for KYC are covered only at controller level and not necessarily through every file edge case
- Daily activity file-screenshot/video upload behaviors remain untested
- Monthly target validations and support ticket workflows remain untested
- Leave and attendance controllers are active, but the broader controller set still remains incomplete

### Missing negative/boundary/exception tests
Examples of missing test categories in current suite:
- Invalid value handling in `BudgetController`
- Overlays and hidden failure branches in `DailyActivityController`
- Empty/duplicate lead creation and validation in `LeadController`
- File upload and storage error branches in `LocalDocumentStorageAdapter`
- SMTP failure handling in mailer classes
- PDO exception paths in repository classes
- Session expiry and tamper branches in `UnlockOtpController`
- Invalid environment / URL provider errors in `EnvironmentFrontendUrlProvider`

## 7. Audit conclusion

### Current evidence-based status
- Backend PHPUnit evidence: 52 tests passed, 191 assertions, 0 failures, 0 errors
- Frontend evidence: 9 tests passed
- Overall Layer 1 status remains BLOCKED

### Why the layer remains blocked
The codebase contains multiple high-risk backend modules and dependency layers that have not been mapped to direct unit coverage. Current successful execution of the active suite is not equivalent to complete Layer 1 proof. The existence of 52 passing tests confirms active coverage for a subset of modules, but it does not satisfy the requirement to audit and test every backend module and every critical business flow.

### Critical unresolved audit gaps
1. `BudgetController` lacks direct unit tests and financial validation coverage.
2. `DailyActivityController` lacks direct tests for upload, validation, and review/approval branches.
3. `LeadController` remains untested.
4. `SalesTargetController` remains untested.
5. `SupportController` remains untested.
6. Database-bound repository classes are not fully isolated for pure unit testing.
7. SMTP, file system, and encryption dependencies remain partially coupled and should be isolated before a stronger Layer 1 pass can be claimed.

## 8. Recommended next testing batches (audit-only recommendation)
The next valid batch sequence should be restricted to backend module-by-module coverage, without making production code changes during this audit phase. Prioritized sequence:

1. Budget and financial controllers
2. Daily activity and support flows
3. Lead management
4. Sales target review workflows
5. Repository and database-failure isolation checks
6. Final cross-module regression and dependency-limited coverage validation

This recommendation preserves the current “BLOCKED” status and avoids premature Layer 2 progression.
