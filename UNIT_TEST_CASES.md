# Unit Test Cases

## Backend test inventory

| ID | Module | Function/Logic | Status | Notes |
|---|---|---|---|---|
| BE-01 | `Arowin\Shared\AppTime` | time normalization | Passed | Shared utility coverage |
| BE-02 | `Arowin\Shared\ValueFormatter` | input validation and formatting | Passed | Shared utility coverage |
| BE-03 | `Arowin\Modules\Employees\EmployeeIdGenerator` | 6-digit generation | Passed | Shared utility coverage |
| BE-04 | `Arowin\Modules\Auth\AuthController` | login/current-user validation | Passed | Auth boundary tests |
| BE-05 | `Arowin\Modules\Sales\SalesController` | team summary and role validation | Passed | Sales controller coverage |
| BE-06 | `Arowin\RouteAuthorization` | authenticated session check | Passed | Validated route-level auth |
| BE-07 | `Arowin\RouteAuthorization` | role restriction rejection | Passed | Validated unauthorized access |
| BE-08 | `CredentialDeliveryService` | prepare/inspect/redeem/resend flow | Passed | Injected repository, mailer, encryption, frontend URL |
| BE-09 | `EmployeeKycController` | valid submission and invalid upload handling | Passed | Injected KYC repository, storage adapter, encryption |
| BE-10 | `UnlockOtpController` | send/verify OTP flow and invalid OTP handling | Passed | Injected challenge store, mailer, clock, OTP generator |

## Critical security scenarios evaluated

- Valid and invalid authentication sessions
- Unauthorized role rejection for route guards
- Credential token creation, inspection, redemption, and resend rotation
- KYC validation for valid and malformed documents
- OTP challenge creation, verification, and invalid/expired input handling
- No production credentials, OTPs, or sensitive KYC values are logged or asserted in plain text

## Summary

The executed backend suite completed with 39 tests and 154 assertions. All active functional tests passed. The project still remains `BLOCKED` at the overall Layer 1 level because the broader project-wide module inventory has not yet been fully evaluated.
