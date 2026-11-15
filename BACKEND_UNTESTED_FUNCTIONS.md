# Backend Untested Functions and Methods

This inventory lists production methods that are currently untested or only indirectly exercised by the active suite. The list was derived from scanning `backend/api` against the current PHPUnit suite under `backend/tests`.

## 1. Uncovered production logic by module

### `budget` / `BudgetController`
Methods identified:
- `__construct`
- `summary`
- `saveCompanyBudget`
- `saveAllocations`
- `listSalaries`
- `saveSalary`
- `validBudgetAmount`
- `amountToCents`
- `validRequiredText`
- `maskAccountNumber`

Audit note:
- No direct PHPUnit file exists for this controller.
- Financial validation and salary persistence rules are open gaps.

### `daily-activities` / `DailyActivityController`
Methods identified:
- `__construct`
- `listActivities`
- `submit`
- `verify`
- `decide`
- `screenshot`
- `video`
- `normalizeUploads`
- `safeOriginalName`
- `uploadErrorMessage`

Audit note:
- No direct tests exist for upload, review, and media validation paths.
- File handling branches remain a major gap.

### `dashboard` / `DashboardController`
Methods identified:
- `__construct`
- `summary`

Audit note:
- No direct coverage exists for dashboard aggregation logic.

### `leads` / `LeadController`
Methods identified:
- `__construct`
- `listForTelecaller`
- `create`

Audit note:
- No direct tests exist for lead-related validation or creation flows.

### `support` / `SupportController`
Methods identified:
- `__construct`
- `listTickets`
- `getTicket`
- `createTicket`
- `reply`
- `text`

Audit note:
- No direct unit tests cover ticket retrieval, creation, or reply logic.

### `targets` / `SalesTargetController`
Methods identified:
- `__construct`
- `listMonthlyTargets`
- `saveMonthlyTarget`
- `verifySubEditorPassword`
- `listHistory`

Audit note:
- No direct tests exist for target submission and authorization checks.

## 2. Repository and database-coupled logic with limited direct unit isolation

### `PdoCredentialRepository`
Methods identified:
- `__construct`
- `insertRecord`
- `findUnusedByUserId`
- `findValidByTokenHash`
- `rotateLatestForUser`
- `markEmailSent`
- `redeemByTokenHash`

Audit note:
- Covered indirectly by `CredentialDeliveryServiceTest.php`.
- Not directly isolated as a repository unit test.

### `PdoKycRepository`
Methods identified:
- `__construct`
- `findByUserId`
- `findExistingDocuments`
- `saveSubmission`

Audit note:
- KYC flow is covered at controller level, but repository-specific edge cases remain untested.

### `PdoUserRepository`
Methods identified:
- `__construct`
- `findSalesEmployeeById`

Audit note:
- OTP verification tests are active, but repository exception/lookup edge paths remain unexamined.

### `LocalDocumentStorageAdapter`
Methods identified:
- `__construct`
- `store`
- `delete`

Audit note:
- File storage and cleanup behavior are not fully isolated and not directly unit-tested.

## 3. Shared helper logic without direct dedicated tests

### `Database`
Methods identified:
- `connect`

Audit note:
- This is a direct PDO bootstrap layer and is not a pure unit-test target in the current suite.

### `EmployeeRoles`
Methods identified:
- `label`
- `normalize`

Audit note:
- Role normalization is exercised indirectly through routing and auth flows, but the helper itself is not isolated in a dedicated unit suite.

### `OtpGenerator`
Methods identified:
- `generate`

Audit note:
- Indirectly exercised through unlock OTP flows but not directly unit-tested.

### `AesGcmEncryptionAdapter`
Methods identified:
- `__construct`
- `fromBase64Env`
- `encrypt`
- `decrypt`

Audit note:
- Indirectly used by KYC/credential flows, but not isolated in a direct utility test.

## 4. Major missing test categories

The untested areas above leave the following categories underrepresented:
- Negative validation paths
- Boundary values and all edge-case branches
- Exception handling and DB failure branches
- File upload handling with invalid MIME or oversized inputs
- Repository and adapter failure behavior
- CORS, session, and environment misconfiguration branches in the routing layer

## 5. Notable finding

The current suite demonstrates strong evidence for a subset of the project, but the remaining untested backend logic still represents a material portion of the business-critical surface area. The missing coverage is not yet acceptable for a complete Layer 1 pass.
