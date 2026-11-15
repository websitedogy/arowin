# Backend Dependency Gap Report

This report focuses on runtime dependencies and architectural seams that limit pure unit isolation in the current backend API.

## 1. Dependency categories discovered

### PDO / MySQL coupling
Observed classes and entry points:
- `backend/api/Database.php`
- `backend/api/modules/auth/AuthController.php`
- `backend/api/modules/attendance/AttendanceController.php`
- `backend/api/modules/budget/BudgetController.php`
- `backend/api/modules/credentials/PdoCredentialRepository.php`
- `backend/api/modules/employees/PdoKycRepository.php`
- `backend/api/modules/sales/PdoUserRepository.php`
- `backend/api/modules/leave/LeaveController.php`
- `backend/api/modules/sales/SalesController.php`
- `backend/api/modules/targets/SalesTargetController.php`
- `backend/api/modules/team-assignments/TeamAssignmentController.php`

Assessment:
- These components are tightly coupled to raw PDO/SQLite/MySQL behavior and are not yet fully abstracted behind repository seams for deterministic unit testing.
- This is a primary reason the overall Layer 1 status remains blocked despite the green backend suite.

### SMTP / mailer dependencies
Observed classes:
- `backend/api/modules/credentials/PhpMailerAdapter.php`
- `backend/api/modules/sales/PhpMailerOtpAdapter.php`
- `backend/api/modules/credentials/CredentialDeliveryService.php`
- `backend/api/modules/sales/UnlockOtpController.php`

Assessment:
- SMTP behavior is a production dependency that must be mocked or ported for isolated tests.
- Current tests partially cover this via injection and mocks, but production integration still depends on live mail configuration.

### PHP session and route-auth state
Observed classes and files:
- `backend/api/index.php`
- `backend/api/RouteAuthorization.php`
- `backend/api/modules/sales/PhpSessionOtpChallengeStore.php`

Assessment:
- Session-backed state is a legitimate part of the application but increases complexity in unit tests unless it is abstracted or injected.
- The store is stateful and thus needs careful test handling to avoid flaky behavior.

### File system and upload handling
Observed classes:
- `backend/api/modules/employees/LocalDocumentStorageAdapter.php`
- `backend/api/modules/employees/EmployeeKycController.php`
- `backend/api/modules/auth/AuthController.php`
- `backend/api/modules/daily-activities/DailyActivityController.php`

Assessment:
- File upload logic, MIME validation, size checks, and cleanup logic remain sensitive to local filesystem behavior and temp-file storage.
- A strong unit test suite should isolate this behind a storage port or temp directory fixtures.

### Encryption and secret handling
Observed classes:
- `backend/api/EncryptionPort.php`
- `backend/api/modules/credentials/AesGcmEncryptionAdapter.php`
- `backend/api/modules/employees/EmployeeKycController.php`
- `backend/api/modules/credentials/CredentialDeliveryService.php`

Assessment:
- Encryption logic contains business-critical security semantics.
- The code is designed to be testable via abstraction, but the system still relies on actual environment-based secret configuration and provider behavior in some paths.

### Global state and environment configuration
Observed files:
- `backend/api/bootstrap.php`
- `backend/api/index.php`
- `backend/api/config/*.php`
- `backend/api/EnvironmentFrontendUrlProvider.php`

Assessment:
- Global variables and getenv-based config make deterministic unit testing harder without explicit mocking or controlled environment setup.
- This is a project-wide testability observation, not a code defect by itself.

## 2. Dependency isolation recommendations (audit-only, no implementation)

### Recommended pattern
- Keep repository contracts and data-access ports at the module boundary.
- Inject repositories, mailers, clock implementations, and storage adapters through constructors.
- Use fake repositories / fake storage / fake clocks in tests.
- Use test-only temp directories and generated keys instead of production config.
- Keep raw PDO and SMTP config in bootstrap, but leave the business logic abstracted from it.

### Priority recommendation by risk
1. Repository isolation for credential, KYC, sales-user, and attendance-heavy flows
2. File-system abstraction for KYC and profile-image uploads
3. Session-store abstraction for OTP verification state
4. Explicit mailer interface isolation for SMTP failures and content validation
5. Environment configuration abstraction for URLs and startup configuration

## 3. Audit conclusion

The dependency gaps are real and they affect test purity, deterministic behavior, and confidence in the broader backend surface. The current green suite proves many business flows work under test doubles, but it does not eliminate the need for a broader dependency-isolation and module-by-module audit before a complete Layer 1 pass can be claimed.
