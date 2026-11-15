# Batch 1 Refactor Implementation Report

## Scope

This batch focused on the dependency-isolation refactor and active test conversion for the four critical modules:

1. Credentials
2. Employee KYC
3. Unlock OTP
4. Route-level authorization

## Files created

- `backend/api/ClockInterface.php`
- `backend/api/SystemClock.php`
- `backend/api/OtpGenerator.php`
- `backend/api/EncryptionPort.php`
- `backend/api/FrontendUrlProvider.php`
- `backend/api/EnvironmentFrontendUrlProvider.php`
- `backend/api/RouteAuthorization.php`
- `backend/api/modules/credentials/CredentialRepository.php`
- `backend/api/modules/credentials/PdoCredentialRepository.php`
- `backend/api/modules/credentials/MailerPort.php`
- `backend/api/modules/credentials/PhpMailerAdapter.php`
- `backend/api/modules/credentials/AesGcmEncryptionAdapter.php`
- `backend/api/modules/employees/KycRepository.php`
- `backend/api/modules/employees/PdoKycRepository.php`
- `backend/api/modules/employees/DocumentStoragePort.php`
- `backend/api/modules/employees/LocalDocumentStorageAdapter.php`
- `backend/api/modules/sales/OtpMailerPort.php`
- `backend/api/modules/sales/PhpMailerOtpAdapter.php`
- `backend/api/modules/sales/OtpChallengeStore.php`
- `backend/api/modules/sales/PhpSessionOtpChallengeStore.php`
- `backend/api/modules/sales/UserRepository.php`
- `backend/api/modules/sales/PdoUserRepository.php`

## Files modified

- `backend/api/modules/credentials/CredentialDeliveryService.php`
- `backend/api/modules/employees/EmployeeKycController.php`
- `backend/api/modules/sales/UnlockOtpController.php`
- `backend/api/index.php`
- `backend/tests/Modules/Credentials/CredentialDeliveryServiceTest.php`
- `backend/tests/Modules/Employees/EmployeeKycControllerTest.php`
- `backend/tests/Modules/Sales/UnlockOtpControllerTest.php`

## Interfaces introduced

- `CredentialRepository`
- `MailerPort`
- `EncryptionPort`
- `FrontendUrlProvider`
- `KycRepository`
- `DocumentStoragePort`
- `OtpMailerPort`
- `OtpChallengeStore`
- `UserRepository`
- `ClockInterface`

## Production dependency wiring

The production runtime still uses the actual backing implementations:

- PDO repositories for credential and KYC persistence
- PHPMailer-based SMTP adapters for outbound emails
- AES-GCM encryption adapter for sensitive values
- PHP session implementation for OTP challenge data
- System clock for time-based expiry rules

The Xdebug coverage extension was installed locally in the user-level PHP directory and loaded with CLI flags only; no production PHP configuration file was edited.

## Test cases executed

Active batch tests:

- CredentialDeliveryServiceTest::testPrepareInspectAndRedeemUseInjectedDependencies
- CredentialDeliveryServiceTest::testResendRotatesLatestCredentialLink
- EmployeeKycControllerTest::testSubmitAcceptsValidKycAndReturnsDecryptedProfile
- EmployeeKycControllerTest::testSubmitRejectsInvalidDocumentAndMalformedPayload
- UnlockOtpControllerTest::testSendAndVerifyOtpFlowUsesInjectedDependencies
- UnlockOtpControllerTest::testVerifyRejectsExpiredAndIncorrectOtps
- Existing auth/route/sales tests from the project suite

## Assertions and results

- Tests executed: 39
- Assertions: 154
- Failures: 0
- Errors: 0
- Warnings: 3
- PHPUnit deprecations: 1

## Compatibility checks

- Credential delivery response structure preserved: token, URL, expiresAt, and same error semantics
- OTP flow preserved: six-digit generation, password-hashed challenge, expiry, attempt counters, and resend throttling
- Route authorization and session behavior remained intact
- KYC business validation rules remained unchanged for payloads, document MIME validation, file constraints, and encryption behavior
- No production database records were modified during testing

## Security checks

- No password, OTP, token, or KYC value was logged in test output.
- No production credentials were used in the tests.
- SMTP, file storage, session, and repository dependencies were replaced with fakes or mocks for isolated business logic tests.
- The refactor avoided weakening existing security controls.

## Remaining limitations

- The broader backend module inventory beyond the critical modules has not yet been exhaustively evaluated.
- The overall Layer 1 project status remains `BLOCKED` until the full backend module map is covered and verified.
- Project-wide coverage is still lower than a full suite completion target, even though the critical modules are now active and passing.
