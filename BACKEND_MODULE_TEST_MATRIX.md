# Backend Module Test Matrix

This matrix maps production modules and core classes to the current PHPUnit evidence and to a coverage classification.

## Classification legend
- FULLY TESTED: direct, meaningful, business-logic coverage exists
- PARTIALLY TESTED: some logic is exercised indirectly, but not isolated or complete
- NOT TESTED: no direct unit coverage exists
- BLOCKED: business logic remains dependent on a live DB or service boundary that prevents reliable unit isolation
- NOT APPLICABLE: not a feature module or no business logic surface in this backend API

| Module | Core component(s) | Existing test file(s) | Status | Notes |
|---|---|---|---|---|
| Attendance | `AttendanceController` | `backend/tests/Modules/Attendance/AttendanceControllerTest.php` | FULLY TESTED | Location, duplicate check-in, history, and attendance validation are covered |
| Auth | `AuthController`, `RouteAuthorization` | `backend/tests/Modules/Auth/AuthControllerTest.php`, `backend/tests/RouteAuthorizationTest.php` | FULLY TESTED | Login, current-user session handling, and permission checks are exercised |
| Budget | `BudgetController` | None | NOT TESTED | Financial calculations and salary persistence are not directly covered |
| Credentials | `CredentialDeliveryService`, `PdoCredentialRepository`, `AesGcmEncryptionAdapter` | `backend/tests/Modules/Credentials/CredentialDeliveryServiceTest.php` | FULLY TESTED (business flow) / BLOCKED (repository isolation) | Link issuance, redemption, and resend are covered; low-level DB isolation remains a gap |
| Daily Activities | `DailyActivityController` | None | NOT TESTED | Upload validation and approval branches are currently untested |
| Dashboard | `DashboardController` | None | NOT TESTED | Aggregations and summary output require direct validation |
| Employees | `EmployeeController`, `EmployeeKycController`, `EmployeeIdGenerator` | `backend/tests/Modules/Employees/EmployeeControllerTest.php`, `backend/tests/Modules/Employees/EmployeeKycControllerTest.php`, `backend/tests/Modules/Employees/EmployeeIdGeneratorTest.php` | FULLY TESTED | Multiple employee lifecycle and KYC validation paths are active |
| Leads | `LeadController` | None | NOT TESTED | Lead creation/listing is not covered |
| Leave | `LeaveController` | `backend/tests/Modules/Leave/LeaveControllerTest.php` | FULLY TESTED | Leave application, overlap checking, and approval logic are validated |
| Sales | `SalesController`, `UnlockOtpController` | `backend/tests/Modules/Sales/SalesControllerTest.php`, `backend/tests/Modules/Sales/UnlockOtpControllerTest.php` | FULLY TESTED | Role filtering, sales summary, and OTP security flows are covered |
| Support | `SupportController` | None | NOT TESTED | Ticket lifecycle and response code handling are not covered |
| Targets | `SalesTargetController` | None | NOT TESTED | Target management and validation are not covered |
| Team Assignments | `TeamAssignmentController` | `backend/tests/Modules/TeamAssignments/TeamAssignmentControllerTest.php` | FULLY TESTED | Assignment creation and leader validation coverage exists |
| Shared utilities | `AppTime`, `ValueFormatter`, `Database`, `EmployeeRoles`, `RouteAuthorization` | `backend/tests/Shared/AppTimeTest.php`, `backend/tests/Shared/ValueFormatterTest.php`, `backend/tests/RouteAuthorizationTest.php` | FULLY TESTED / PARTIALLY TESTED | Utility validation is active; direct DB bootstrap is not isolated |

## Risk summary by module

| Risk area | Module(s) | Current classification |
|---|---|---|
| Financial calculations | `budget` | NOT TESTED |
| Attendance rules | `attendance` | FULLY TESTED |
| Leave and business validation | `leave` | FULLY TESTED |
| KYC processing | `employees` | FULLY TESTED |
| Credential issuance/redemption | `credentials` | FULLY TESTED (business flow) |
| OTP expiry/attempt logic | `sales` | FULLY TESTED |
| Auth + authorization | `auth`, `route auth` | FULLY TESTED |
| Support ticket workflows | `support` | NOT TESTED |
| Sales target management | `targets` | NOT TESTED |
| Team assignment integrity | `team-assignments` | FULLY TESTED |

## Overall matrix conclusion

The project has active, meaningful unit tests for a core subset of the backend API, but there are material gaps in the remaining business-facing modules. The current evidence does not satisfy a complete Layer 1 audit because several key controllers and repository/adapter layers remain either untested or blocked from pure unit isolation.
