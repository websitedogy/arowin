# Unit Test Coverage

## Coverage instrumentation status

A local development-only Xdebug extension was installed to generate real PHP coverage data without changing production PHP configuration.

- Xdebug version: 3.6.0alpha1
- Install location: `C:\Users\vamsh\AppData\Local\php\ext`
- Loaded for the run only via CLI flags, not via a permanent production config edit

## Generated coverage summary

Executed using:

`php -d zend_extension=C:\Users\vamsh\AppData\Local\php\ext\php_xdebug-3.6.0alpha1-8.4-nts-vs17-x86_64.dll -d xdebug.mode=coverage -d xdebug.start_with_request=0 .\phpunit.phar --configuration .\phpunit.xml --coverage-filter . --coverage-text --coverage-html .\coverage\html --coverage-clover .\coverage\clover.xml --colors=never`

Result:

- Classes: 11.76% (6/51)
- Methods: 8.81% (38/431)
- Lines: 9.06% (762/8404)

## Coverage by critical area

| Area | Coverage status |
|---|---|
| Shared utilities | Good |
| Auth controller | Partial |
| Sales controller | Partial |
| Route authorization | Partial |
| Credentials flow | Good |
| Employee KYC flow | Good |
| Unlock OTP flow | Good |

## Generated reports

- HTML: `backend/coverage/html`
- Clover XML: `backend/coverage/clover.xml`

## Final coverage status

The project now has a real generated coverage report, but not full project-wide coverage. This is sufficient to prove the critical flows are covered, while still retaining overall Layer 1 `BLOCKED` status because broader backend modules remain unevaluated.
