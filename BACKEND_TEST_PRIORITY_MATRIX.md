# Backend Test Priority Matrix

This matrix prioritizes production risk and test urgency for the backend API based on the current audit.

## Priority legend
- P0: Critical business logic and security-sensitive flows
- P1: Important operational logic with user-facing impact
- P2: Lower-risk administrative or report-oriented logic

| Priority | Module / area | Production component(s) | Audit status | Why it matters |
|---|---|---|---|---|
| P0 | Credential issuance and redemption | `CredentialDeliveryService`, `PdoCredentialRepository` | FULLY TESTED (business flow), BLOCKED for isolation at DB layer | Token hashing, expiry, rotation, and redemption are security-critical |
| P0 | KYC validation and storage | `EmployeeKycController`, `PdoKycRepository`, `LocalDocumentStorageAdapter` | FULLY TESTED (controller); PARTIALLY TESTED (storage/repo) | KYC data includes encrypted identity and financial information |
| P0 | OTP verification and lockout | `UnlockOtpController`, `PhpSessionOtpChallengeStore`, `PdoUserRepository` | FULLY TESTED (business flow) | Expiry, invalid OTP, replay, and attempt limits are security-sensitive |
| P0 | Route auth and session rules | `RouteAuthorization`, `index.php` | FULLY TESTED | Prevents unauthorized access and protects business workflows |
| P0 | Attendance validation | `AttendanceController` | FULLY TESTED | Office-distance validation and attendance status logic influence payroll/attendance integrity |
| P0 | Leave validation | `LeaveController` | FULLY TESTED | Overlap checks and approval logic have business impact |
| P1 | Employee lifecycle | `EmployeeController`, `EmployeeIdGenerator` | FULLY TESTED | Employee creation and validation are core workflows |
| P1 | Sales summary and role checks | `SalesController` | FULLY TESTED | Sales totals and role restrictions are operationally important |
| P1 | Team assignment logic | `TeamAssignmentController` | FULLY TESTED | Assignment integrity affects the sales and support hierarchy |
| P1 | Budget and salary accounting | `BudgetController` | NOT TESTED | Financial data and salary allocations require validation |
| P1 | Daily activity pipeline | `DailyActivityController` | NOT TESTED | Media validation and review logic are business-critical but currently uncovered |
| P2 | Dashboard summary | `DashboardController` | NOT TESTED | Reporting and summary tasks have business value but lower risk than core auth workflows |
| P2 | Lead management | `LeadController` | NOT TESTED | Important operational workflow, but not a direct security-critical path |
| P2 | Support ticket lifecycle | `SupportController` | NOT TESTED | Less critical than auth, KYC, or sales logic |
| P2 | Sales target management | `SalesTargetController` | NOT TESTED | Operationally relevant but secondary compared with core risk layers |

## Batch recommendation based on priority

1. P0 modules first: credential, KYC, OTP, auth, attendance, leave
2. P1 modules next: employee lifecycle, sales, team assignments, budget, daily activities
3. P2 modules last: dashboard, leads, support, sales targets

## Current audit outcome

The highest-risk components are substantially covered, but the remaining P1/P2 modules and repository-isolation gaps still block a complete Layer 1 claim. This is why the overall status remains BLOCKED.
