# Unit Test Batch 1 Report

## Scope

This batch focused on four high-risk modules:

1. Credentials
2. Employee KYC
3. Unlock OTP
4. Route-level authorization

## Batch 1 execution summary

Executed using a local Xdebug install and coverage filter:

- Command: `php -d zend_extension=C:\Users\vamsh\AppData\Local\php\ext\php_xdebug-3.6.0alpha1-8.4-nts-vs17-x86_64.dll -d xdebug.mode=coverage -d xdebug.start_with_request=0 .\phpunit.phar --configuration .\phpunit.xml --coverage-filter . --coverage-text --coverage-html .\coverage\html --coverage-clover .\coverage\clover.xml --colors=never`
- Result: `OK, but there were issues!`
- Tests: 39
- Assertions: 154
- Failures: 0
- Errors: 0
- Warnings: 3
- PHPUnit deprecations: 1
- Final batch status: `BLOCKED` at overall Layer 1, but critical module tests are active and passing

## Module 1: Credentials

### Functions analyzed

- `CredentialDeliveryService::prepare()`
- `CredentialDeliveryService::inspect()`
- `CredentialDeliveryService::redeem()`
- `CredentialDeliveryService::resend()`
- `CredentialDeliveryService::markEmailSent()`

### Test cases created

- `testPrepareInspectAndRedeemUseInjectedDependencies`
- `testResendRotatesLatestCredentialLink`

### Assertions

- 2 active test methods
- 2+ meaningful assertions covering valid prepare/inspect/redeem flow and resend rotation

### Passed and failed tests

- Passed: 2
- Failed: 0

### Remaining isolation gaps

- None for the specific business flow after refactoring to injected repository, mailer, encryption, and frontend URL abstractions.

### Security observations

- Token hashes and passwords are not logged.
- Only mock and fake implementations were used in unit tests.

## Module 2: Employee KYC

### Functions analyzed

- `EmployeeKycController::submit()`
- `EmployeeKycController::get()` via repository-backed return path
- document validation and invalid-file rejection

### Test cases created

- `testSubmitAcceptsValidKycAndReturnsDecryptedProfile`
- `testSubmitRejectsInvalidDocumentAndMalformedPayload`

### Assertions

- 2 active tests covering valid submission and invalid document rejection

### Passed and failed tests

- Passed: 2
- Failed: 0

### Remaining isolation gaps

- None in the tested flow; the controller is now mockable via repository, encryption port, and storage adapter.

### Security observations

- Aadhaar and PAN values are kept out of logs and test output.
- MIME restrictions and document limits were preserved.

## Module 3: Unlock OTP

### Functions analyzed

- `UnlockOtpController::send()`
- `UnlockOtpController::verify()`
- challenge state and verification logic

### Test cases created

- `testSendAndVerifyOtpFlowUsesInjectedDependencies`
- `testVerifyRejectsExpiredAndIncorrectOtps`

### Assertions

- 2 active tests covering valid verification and invalid/expired OTP rejection

### Passed and failed tests

- Passed: 2
- Failed: 0

### Remaining isolation gaps

- None in the core OTP flow after dependency injection.

### Security observations

- OTP values are not exposed in logs.
- The test validates attempt limits and verification boundaries without weakening expiry or security rules.

## Module 4: Route-level authorization

### Functions analyzed

- `RouteAuthorization::ensureAuthenticated()`
- `RouteAuthorization::ensureRole()`
- role-specific route restrictions

### Test cases created

- 3 active assertions covering:
  - authenticated session allowed
  - unauthenticated session rejected
  - unauthorized role denied

### Assertions

- 6 assertions executed

### Passed and failed tests

- Passed: 3 assertions, 0 failed
- Failed: 0

### Security observations

- The auth boundary is enforced without exposing session data or user identity in logs.

## Final batch decision

Status: BLOCKED overall Layer 1, but the critical modules are active and passing.

Reason: The critical modules are now green, but the broader backend inventory has not yet been fully evaluated. This means the project cannot be declared fully PASS at the full Layer 1 level until the wider suite is assessed and documented.
