<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use Arowin\Database;
use Arowin\Modules\Auth\AuthController;
use Arowin\Modules\Attendance\AttendanceController;
use Arowin\Modules\Budget\BudgetController;
use Arowin\Modules\Credentials\CredentialDeliveryService;
use Arowin\Modules\DailyActivities\DailyActivityController;
use Arowin\Modules\Dashboard\DashboardController;
use Arowin\Modules\Employees\EmployeeController;
use Arowin\Modules\Employees\EmployeeKycController;
use Arowin\Modules\Leave\LeaveController;
use Arowin\Modules\Leads\LeadController;
use Arowin\Modules\TeamAssignments\TeamAssignmentController;
use Arowin\Modules\Targets\SalesTargetController;
use Arowin\Modules\Sales\SalesController;
use Arowin\Modules\Sales\UnlockOtpController;
use Arowin\Modules\Support\SupportController;
use Arowin\Shared\AppTime;

$allowedOrigins = array_filter(array_map(
    'trim',
    explode(',', getenv('AROWIN_FRONTEND_ORIGINS') ?: 'http://localhost:5173,http://127.0.0.1:5173')
));
$requestOrigin = $_SERVER['HTTP_ORIGIN'] ?? '';

if (in_array($requestOrigin, $allowedOrigins, true)) {
    header('Access-Control-Allow-Origin: ' . $requestOrigin);
    header('Access-Control-Allow-Credentials: true');
    header('Vary: Origin');
}

header('Access-Control-Allow-Headers: Content-Type, X-Arowin-Session');
header('Access-Control-Expose-Headers: X-Arowin-Session');
header('Access-Control-Allow-Methods: DELETE, GET, POST, OPTIONS');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_name('AROWIN_TAB_SESSION');
    ini_set('session.use_cookies', '0');
    ini_set('session.use_only_cookies', '0');
    ini_set('session.use_strict_mode', '1');
    $tabSession = $_SERVER['HTTP_X_AROWIN_SESSION'] ?? '';
    if ($tabSession !== '') {
        if (!preg_match('/^[A-Za-z0-9,-]{16,128}$/', $tabSession)) {
            jsonResponse(['success' => false, 'message' => 'Invalid tab session. Refresh this page and sign in again.'], 400);
        }
        session_id($tabSession);
    }
    session_start();
}

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if (is_string($path) && preg_match('#^/api/credentials/download/([a-f0-9]{64})$#', $path, $matches)) {
    header('Cache-Control: no-store, private');
    header('Referrer-Policy: no-referrer');

    try {
        $delivery = new CredentialDeliveryService(Database::connect());
        $credentialToken = $matches[1];

        if ($method === 'GET') {
            jsonResponse(['success' => true, 'credential' => $delivery->inspect($credentialToken)]);
        }

        if ($method === 'POST') {
            jsonResponse(['success' => true, 'credentials' => $delivery->redeem($credentialToken)]);
        }
    } catch (DomainException $error) {
        jsonResponse(['success' => false, 'message' => $error->getMessage()], 410);
    } catch (RuntimeException $error) {
        error_log($error->getMessage());
        jsonResponse(['success' => false, 'message' => 'The credential link could not be verified. Contact your AROWIN administrator.'], 503);
    } catch (Throwable $error) {
        error_log((string) $error);
        jsonResponse(['success' => false, 'message' => 'The credential link could not be verified.'], 500);
    }

    jsonResponse(['success' => false, 'message' => 'Method not allowed.'], 405);
}

try {
    if ($path === '/api/auth/login' && $method === 'POST') {
        $controller = new AuthController(Database::connect());
        $result = $controller->login(parseJsonBody());
        jsonResponse($result, ($result['success'] ?? false) ? 200 : 401);
    }

    if ($path === '/api/auth/logout' && $method === 'POST') {
        AuthController::logout();
        jsonResponse(['success' => true]);
    }

    if ($path === '/api/auth/me' && $method === 'GET') {
        if (!isset($_SESSION['user'])) {
            jsonResponse(['success' => false, 'message' => 'Sign in to continue.'], 401);
        }

        $user = (new AuthController(Database::connect()))->currentUser((int) $_SESSION['user']['id']);
        if ($user === null) {
            unset($_SESSION['user']);
            jsonResponse(['success' => false, 'message' => 'Sign in to continue.'], 401);
        }

        $_SESSION['user'] = $user;
        jsonResponse(['success' => true, 'user' => $_SESSION['user']]);
    }

    if ($path === '/api/employee-kyc' && in_array($method, ['GET', 'POST'], true)) {
        $userId = requireKycEmployee();
        header('Cache-Control: private, no-store');
        $kyc = new EmployeeKycController(Database::connect());
        if ($method === 'GET') {
            jsonResponse(['success' => true, 'kyc' => $kyc->get($userId)]);
        }
        jsonResponse([
            'success' => true,
            'kyc' => $kyc->submit(
                $userId,
                $_POST,
                $_FILES['aadhaar_document'] ?? null,
                $_FILES['pan_document'] ?? null
            ),
            'message' => 'Your KYC details have been submitted securely.',
        ]);
    }

    if ($path === '/api/auth/profile-image' && in_array($method, ['GET', 'POST'], true)) {
        requireAuthenticated();
        if (!in_array($_SESSION['user']['role'], ['team_leader', 'telecaller'], true)) {
            jsonResponse(['success' => false, 'message' => 'Only employees can manage a profile photo.'], 403);
        }

        $auth = new AuthController(Database::connect());
        if ($method === 'POST') {
            $auth->updateProfileImage((int) $_SESSION['user']['id'], $_FILES['profile_image'] ?? null);
            $user = $auth->currentUser((int) $_SESSION['user']['id']);
            if ($user === null) {
                unset($_SESSION['user']);
                jsonResponse(['success' => false, 'message' => 'Sign in to continue.'], 401);
            }
            $_SESSION['user'] = $user;
            jsonResponse(['success' => true, 'user' => $user, 'message' => 'Profile photo updated.']);
        }

        $image = $auth->profileImage((int) $_SESSION['user']['id']);
        if ($image === null) {
            jsonResponse(['success' => false, 'message' => 'No profile photo has been uploaded.'], 404);
        }

        header('Content-Type: ' . $image['mimeType']);
        header('Content-Length: ' . (string) filesize($image['path']));
        header('Cache-Control: private, no-store');
        header('X-Content-Type-Options: nosniff');
        readfile($image['path']);
        exit;
    }

    if ($path === '/api/employees' && $method === 'GET') {
        requireSubEditor();
        $employees = (new EmployeeController(Database::connect()))->listEmployees();
        jsonResponse(['success' => true, 'employees' => $employees]);
    }

    if ($path === '/api/employees' && $method === 'POST') {
        requireSubEditor();
        $created = (new EmployeeController(Database::connect()))->createEmployee(
            parseJsonBody(),
            (int) $_SESSION['user']['id']
        );
        jsonResponse([
            'success' => true,
            'employee' => $created['employee'],
            'activity' => $created['activity'],
            'emailDelivery' => $created['emailDelivery'],
        ], 201);
    }

    if ($path === '/api/budget' && $method === 'GET') {
        requireSubEditor();
        jsonResponse(['success' => true, 'budget' => (new BudgetController(Database::connect()))->summary()]);
    }

    if ($path === '/api/budget/company' && $method === 'POST') {
        requireSubEditor();
        $budget = (new BudgetController(Database::connect()))->saveCompanyBudget(
            parseJsonBody(),
            (int) $_SESSION['user']['id']
        );
        jsonResponse(['success' => true, 'budget' => $budget]);
    }

    if ($path === '/api/budget/allocations' && $method === 'POST') {
        requireSubEditor();
        $result = (new BudgetController(Database::connect()))->saveAllocations(
            parseJsonBody(),
            (int) $_SESSION['user']['id']
        );
        jsonResponse(['success' => true] + $result);
    }

    if ($path === '/api/budget/salaries' && $method === 'GET') {
        requireSubEditor();
        $salaries = (new BudgetController(Database::connect()))->listSalaries();
        jsonResponse(['success' => true, 'salaries' => $salaries]);
    }

    if (is_string($path) && preg_match('#^/api/budget/salaries/([1-9][0-9]*)$#', $path, $matches) && $method === 'POST') {
        requireSubEditor();
        $salary = (new BudgetController(Database::connect()))->saveSalary(
            (int) $matches[1],
            parseJsonBody(),
            (int) $_SESSION['user']['id']
        );
        jsonResponse(['success' => true, 'salary' => $salary]);
    }

    if ($path === '/api/attendance/settings' && $method === 'GET') {
        requireSubEditor();
        jsonResponse([
            'success' => true,
            'settings' => (new AttendanceController(Database::connect()))->settings(),
        ]);
    }

    if ($path === '/api/attendance/settings' && $method === 'POST') {
        requireSubEditor();
        $settings = (new AttendanceController(Database::connect()))->saveOnTimeBonusAmount(
            parseJsonBody(),
            (int) $_SESSION['user']['id']
        );
        jsonResponse(['success' => true, 'settings' => $settings]);
    }

    if ($path === '/api/daily-activities/settings' && $method === 'GET') {
        requireSubEditor();
        $settings = (new DailyActivityController(Database::connect()))->bonusSettings();
        jsonResponse(['success' => true, 'settings' => $settings]);
    }

    if ($path === '/api/daily-activities/settings' && $method === 'POST') {
        requireSubEditor();
        $settings = (new DailyActivityController(Database::connect()))->saveBonusSettings(
            parseJsonBody(),
            (int) $_SESSION['user']['id']
        );
        jsonResponse(['success' => true, 'settings' => $settings]);
    }

    if ($path === '/api/attendance/office-location' && $method === 'GET') {
        requireSubEditor();
        jsonResponse([
            'success' => true,
            'officeLocation' => (new AttendanceController(Database::connect()))->officeLocation(),
        ]);
    }

    if ($path === '/api/attendance/holidays' && $method === 'GET') {
        requireSubEditor();
        jsonResponse([
            'success' => true,
            'holidays' => (new AttendanceController(Database::connect()))->listHolidays(),
        ]);
    }

    if ($path === '/api/attendance/holidays' && $method === 'POST') {
        requireSubEditor();
        $holiday = (new AttendanceController(Database::connect()))->saveHoliday(
            parseJsonBody(),
            (int) $_SESSION['user']['id']
        );
        jsonResponse(['success' => true, 'holiday' => $holiday], 201);
    }

    if (is_string($path) && preg_match('#^/api/attendance/holidays/([0-9]{4}-[0-9]{2}-[0-9]{2})$#', $path, $matches) && $method === 'DELETE') {
        requireSubEditor();
        (new AttendanceController(Database::connect()))->deleteHoliday($matches[1]);
        jsonResponse(['success' => true]);
    }

    if ($path === '/api/leave-requests' && $method === 'GET') {
        requireAuthenticated();
        $userId = $_SESSION['user']['role'] === 'sub_editor'
            ? null
            : requireLeaveApplicant();
        jsonResponse([
            'success' => true,
            'requests' => (new LeaveController(Database::connect()))->listRequests($userId),
        ]);
    }

    if ($path === '/api/leave-requests' && $method === 'POST') {
        $userId = requireLeaveApplicant();
        $request = (new LeaveController(Database::connect()))->apply($userId, parseJsonBody());
        jsonResponse(['success' => true, 'request' => $request], 201);
    }

    if (is_string($path) && preg_match('#^/api/leave-requests/([1-9][0-9]*)/decision$#', $path, $matches) && $method === 'POST') {
        requireSubEditor();
        $payload = parseJsonBody();
        (new LeaveController(Database::connect()))->decide(
            (int) $matches[1],
            (int) $_SESSION['user']['id'],
            is_string($payload['decision'] ?? null) ? $payload['decision'] : ''
        );
        jsonResponse(['success' => true]);
    }

    if ($path === '/api/support/tickets' && $method === 'GET') {
        requireAuthenticated();
        $role = (string) $_SESSION['user']['role'];
        if (!in_array($role, ['sub_editor', 'team_leader', 'telecaller'], true)) {
            jsonResponse(['success' => false, 'message' => 'You do not have permission to access support tickets.'], 403);
        }
        jsonResponse([
            'success' => true,
            'tickets' => (new SupportController(Database::connect()))->listTickets(
                $role === 'sub_editor' ? null : (int) $_SESSION['user']['id']
            ),
        ]);
    }

    if ($path === '/api/support/tickets' && $method === 'POST') {
        requireAuthenticated();
        $userId = requireSupportEmployee();
        $ticketId = (new SupportController(Database::connect()))->createTicket($userId, parseJsonBody());
        jsonResponse(['success' => true, 'ticketId' => $ticketId], 201);
    }

    if (is_string($path) && preg_match('#^/api/support/tickets/([1-9][0-9]*)$#', $path, $matches) && $method === 'GET') {
        requireAuthenticated();
        $role = (string) $_SESSION['user']['role'];
        if (!in_array($role, ['sub_editor', 'team_leader', 'telecaller'], true)) {
            jsonResponse(['success' => false, 'message' => 'You do not have permission to access support tickets.'], 403);
        }
        jsonResponse([
            'success' => true,
            'ticket' => (new SupportController(Database::connect()))->getTicket(
                (int) $matches[1],
                (int) $_SESSION['user']['id'],
                $role === 'sub_editor'
            ),
        ]);
    }

    if (is_string($path) && preg_match('#^/api/support/tickets/([1-9][0-9]*)/messages$#', $path, $matches) && $method === 'POST') {
        requireAuthenticated();
        $role = (string) $_SESSION['user']['role'];
        if (!in_array($role, ['sub_editor', 'team_leader', 'telecaller'], true)) {
            jsonResponse(['success' => false, 'message' => 'You do not have permission to reply to support tickets.'], 403);
        }
        (new SupportController(Database::connect()))->reply(
            (int) $matches[1],
            (int) $_SESSION['user']['id'],
            $role === 'sub_editor',
            parseJsonBody()
        );
        jsonResponse(['success' => true]);
    }

    if ($path === '/api/daily-activities' && $method === 'GET') {
        requireAuthenticated();
        $userId = $_SESSION['user']['role'] === 'sub_editor'
            ? null
            : requireDailyActivityEmployee();
        jsonResponse([
            'success' => true,
            'activities' => (new DailyActivityController(Database::connect()))->listActivities($userId),
        ]);
    }

    if ($path === '/api/daily-activities' && $method === 'POST') {
        $userId = requireDailyActivityEmployee();
        $activity = (new DailyActivityController(Database::connect()))->submit(
            $userId,
            $_POST,
            $_FILES['screenshots'] ?? null,
            $_FILES['videos'] ?? null
        );
        jsonResponse(['success' => true, 'activity' => $activity], 201);
    }

    if (is_string($path) && preg_match('#^/api/daily-activities/([1-9][0-9]*)/decision$#', $path, $matches) && $method === 'POST') {
        requireSubEditor();
        $payload = parseJsonBody();
        (new DailyActivityController(Database::connect()))->decide(
            (int) $matches[1],
            (int) $_SESSION['user']['id'],
            is_string($payload['decision'] ?? null) ? $payload['decision'] : '',
            is_string($payload['reason'] ?? null) ? $payload['reason'] : null
        );
        jsonResponse(['success' => true]);
    }

    if (is_string($path) && preg_match('#^/api/daily-activities/([1-9][0-9]*)/verify$#', $path, $matches) && $method === 'POST') {
        requireSubEditor();
        (new DailyActivityController(Database::connect()))->verify(
            (int) $matches[1],
            (int) $_SESSION['user']['id']
        );
        jsonResponse(['success' => true]);
    }

    if (is_string($path) && preg_match('#^/api/daily-activities/([1-9][0-9]*)/screenshots/([1-9][0-9]*)$#', $path, $matches) && $method === 'GET') {
        requireAuthenticated();
        $userId = $_SESSION['user']['role'] === 'sub_editor'
            ? null
            : requireDailyActivityEmployee();
        $screenshot = (new DailyActivityController(Database::connect()))->screenshot(
            (int) $matches[2],
            (int) $matches[1],
            $userId
        );
        header('Content-Type: ' . $screenshot['mimeType']);
        header('Content-Disposition: inline; filename*=UTF-8\'\'' . rawurlencode($screenshot['name']));
        header('Content-Length: ' . (string) filesize($screenshot['path']));
        header('Cache-Control: private, no-store');
        header('X-Content-Type-Options: nosniff');
        readfile($screenshot['path']);
        exit;
    }

    if (is_string($path) && preg_match('#^/api/daily-activities/([1-9][0-9]*)/videos/([1-9][0-9]*)$#', $path, $matches) && $method === 'GET') {
        requireAuthenticated();
        $userId = $_SESSION['user']['role'] === 'sub_editor'
            ? null
            : requireDailyActivityEmployee();
        $video = (new DailyActivityController(Database::connect()))->video(
            (int) $matches[2],
            (int) $matches[1],
            $userId
        );
        header('Content-Type: ' . $video['mimeType']);
        header('Content-Disposition: inline; filename*=UTF-8\'\'' . rawurlencode($video['name']));
        header('Content-Length: ' . (string) filesize($video['path']));
        header('Cache-Control: private, no-store');
        header('X-Content-Type-Options: nosniff');
        readfile($video['path']);
        exit;
    }

    if ($path === '/api/attendance/me' && $method === 'GET') {
        requireAttendanceEmployee();
        jsonResponse([
            'success' => true,
            'attendance' => (new AttendanceController(Database::connect()))->currentAttendance((int) $_SESSION['user']['id']),
        ]);
    }

    if ($path === '/api/attendance/history' && $method === 'GET') {
        requireAttendanceEmployee();
        $month = isset($_GET['month']) && is_string($_GET['month'])
            ? $_GET['month']
            : AppTime::now()->format('Y-m');
        jsonResponse([
            'success' => true,
            'history' => (new AttendanceController(Database::connect()))->history(
                (int) $_SESSION['user']['id'],
                $month
            ),
        ]);
    }

    if ($path === '/api/attendance/location-check' && $method === 'POST') {
        requireAttendanceEmployee();
        jsonResponse([
            'success' => true,
            'location' => (new AttendanceController(Database::connect()))->verifyLocation(parseJsonBody()),
        ]);
    }

    if ($path === '/api/attendance/check-in' && $method === 'POST') {
        requireAttendanceEmployee();
        jsonResponse([
            'success' => true,
            'attendance' => (new AttendanceController(Database::connect()))->checkIn(
                (int) $_SESSION['user']['id'],
                parseJsonBody()
            ),
        ], 201);
    }

    if ($path === '/api/attendance/check-out' && $method === 'POST') {
        requireAttendanceEmployee();
        jsonResponse([
            'success' => true,
            'attendance' => (new AttendanceController(Database::connect()))->checkOut(
                (int) $_SESSION['user']['id'],
                parseJsonBody()
            ),
        ]);
    }

    if ($path === '/api/team-assignments' && $method === 'GET') {
        requireSubEditor();
        $assignments = new TeamAssignmentController(Database::connect());
        jsonResponse(['success' => true, 'teamLeaders' => $assignments->listTeamLeaders()]);
    }

    if ($path === '/api/sales-targets' && $method === 'GET') {
        requireSubEditor();
        $targets = new SalesTargetController(Database::connect());
        jsonResponse([
            'success' => true,
            'targets' => $targets->listMonthlyTargets((string) ($_GET['month'] ?? date('Y-m'))),
        ]);
    }

    if (is_string($path) && preg_match('#^/api/sales-targets/([1-9][0-9]*)$#', $path, $matches) && $method === 'POST') {
        requireSubEditor();
        $targets = new SalesTargetController(Database::connect());
        $payload = parseJsonBody();
        $confirmationPassword = is_string($payload['password'] ?? null) ? $payload['password'] : '';
        if (!$targets->verifySubEditorPassword(
            (int) $_SESSION['user']['id'],
            $confirmationPassword
        )) {
            jsonResponse(['success' => false, 'message' => 'The Sub Editor password is incorrect.'], 403);
        }

        jsonResponse([
            'success' => true,
            'target' => $targets->saveMonthlyTarget(
                (int) $matches[1],
                $payload,
                (int) $_SESSION['user']['id']
            ),
        ]);
    }

    if ($path === '/api/sales-targets/reward-settings' && $method === 'GET') {
        requireSubEditor();
        $sales = new SalesController(Database::connect());
        jsonResponse(['success' => true, 'settings' => $sales->teamTargetRewardSettings()]);
    }

    if ($path === '/api/sales-targets/reward-settings' && $method === 'POST') {
        requireSubEditor();
        $payload = parseJsonBody();
        $rewardAmount = $payload['rewardAmount'] ?? null;
        if (!is_string($rewardAmount) && !is_int($rewardAmount) && !is_float($rewardAmount)) {
            jsonResponse(['success' => false, 'message' => 'Enter a valid Team Leader target reward in USDT.'], 422);
        }
        $sales = new SalesController(Database::connect());
        jsonResponse([
            'success' => true,
            'settings' => $sales->saveTeamTargetRewardAmount((string) $rewardAmount, (int) $_SESSION['user']['id']),
        ]);
    }

    if ($path === '/api/sales-targets/team-grant-incentive-settings' && $method === 'GET') {
        requireSubEditor();
        $sales = new SalesController(Database::connect());
        jsonResponse(['success' => true, 'settings' => $sales->teamGrantIncentiveSettings()]);
    }

    if ($path === '/api/sales-targets/team-grant-incentive-settings' && $method === 'POST') {
        requireSubEditor();
        $payload = parseJsonBody();
        $leaderSharePercent = $payload['leaderSharePercent'] ?? null;
        if (!is_string($leaderSharePercent) && !is_int($leaderSharePercent) && !is_float($leaderSharePercent)) {
            jsonResponse(['success' => false, 'message' => 'Enter a valid Team Leader incentive share percentage.'], 422);
        }
        $sales = new SalesController(Database::connect());
        jsonResponse([
            'success' => true,
            'settings' => $sales->saveTeamGrantIncentivePercentage(
                (string) $leaderSharePercent,
                (int) $_SESSION['user']['id']
            ),
        ]);
    }

    if ($path === '/api/sales/performance' && $method === 'GET') {
        requireAuthenticated();
        $sales = new SalesController(Database::connect());
        jsonResponse([
            'success' => true,
            'performance' => $sales->performance(
                (int) $_SESSION['user']['id'],
                (string) $_SESSION['user']['role'],
                (string) ($_GET['month'] ?? AppTime::now()->format('Y-m'))
            ),
        ]);
    }

    if ($path === '/api/leads' && in_array($method, ['GET', 'POST'], true)) {
        requireAuthenticated();
        if ($_SESSION['user']['role'] !== 'telecaller') {
            jsonResponse(['success' => false, 'message' => 'Only Telecallers can manage their leads.'], 403);
        }

        $leads = new LeadController(Database::connect());
        if ($method === 'GET') {
            jsonResponse([
                'success' => true,
                'leads' => $leads->listForTelecaller((int) $_SESSION['user']['id']),
            ]);
        }

        jsonResponse([
            'success' => true,
            'lead' => $leads->create((int) $_SESSION['user']['id'], parseJsonBody()),
        ], 201);
    }

    if ($path === '/api/sales/summary' && $method === 'GET') {
        requireSubEditor();
        $sales = new SalesController(Database::connect());
        jsonResponse([
            'success' => true,
            'summary' => $sales->subEditorSummary((string) ($_GET['period'] ?? 'total')),
        ]);
    }

    if ($path === '/api/sales/packages' && $method === 'GET') {
        requireAuthenticated();
        if (!in_array($_SESSION['user']['role'], ['sub_editor', 'telecaller', 'team_leader'], true)) {
            jsonResponse(['success' => false, 'message' => 'You do not have permission to view unlock packages.'], 403);
        }
        $sales = new SalesController(Database::connect());
        jsonResponse(['success' => true, 'packages' => $sales->listUnlockPackages()]);
    }

    if ($path === '/api/sales/packages/incentives' && $method === 'POST') {
        requireSubEditor();
        $sales = new SalesController(Database::connect());
        jsonResponse([
            'success' => true,
            'packages' => $sales->configureUnlockPackages(
                parseJsonBody(),
                (int) $_SESSION['user']['id']
            ),
        ]);
    }

    if ($path === '/api/sales/unlocks/me' && $method === 'GET') {
        requireSalesEmployee();
        $sales = new SalesController(Database::connect());
        jsonResponse([
            'success' => true,
            'result' => $sales->employeeUnlockSales((int) $_SESSION['user']['id']),
        ]);
    }

    if ($path === '/api/sales/unlocks' && $method === 'POST') {
        requireSalesEmployee();
        $verified = $_SESSION['unlock_sales_otp_verified'] ?? null;
        if (
            !is_array($verified)
            || (int) ($verified['userId'] ?? 0) !== (int) $_SESSION['user']['id']
            || (int) ($verified['expiresAt'] ?? 0) < time()
        ) {
            unset($_SESSION['unlock_sales_otp_verified']);
            jsonResponse(['success' => false, 'message' => 'Verify the email OTP before submitting a sale for approval.'], 409);
        }
        $sales = new SalesController(Database::connect());
        unset($_SESSION['unlock_sales_otp_verified']);
        $sale = $sales->submitUnlockSale();
        jsonResponse(['success' => true, 'sale' => $sale], 201);
    }

    if ($path === '/api/sales/unlocks' && $method === 'GET') {
        requireSubEditor();
        $sales = new SalesController(Database::connect());
        jsonResponse([
            'success' => true,
            'result' => $sales->listUnlockSalesForReview((string) ($_GET['status'] ?? 'all')),
        ]);
    }

    if (is_string($path) && preg_match('#^/api/sales/unlocks/([1-9][0-9]*)/review$#', $path, $matches) && $method === 'POST') {
        requireSubEditor();
        $sales = new SalesController(Database::connect());
        jsonResponse([
            'success' => true,
            'sale' => $sales->reviewUnlockSale(
                (int) $matches[1],
                parseJsonBody(),
                (int) $_SESSION['user']['id']
            ),
        ]);
    }

    if ($path === '/api/sales/incentives/me' && $method === 'GET') {
        requireSalesEmployee();
        $sales = new SalesController(Database::connect());
        jsonResponse([
            'success' => true,
            'result' => $sales->employeeIncentives((int) $_SESSION['user']['id']),
        ]);
    }

    if ($path === '/api/sales/team-achievements' && $method === 'GET') {
        requireSalesEmployee();
        $sales = new SalesController(Database::connect());
        jsonResponse([
            'success' => true,
            'result' => $sales->teamUnlockAchievements(
                (int) $_SESSION['user']['id'],
                (string) $_SESSION['user']['role']
            ),
        ]);
    }

    if ($path === '/api/sales/team-incentives' && $method === 'GET') {
        requireAuthenticated();
        if ($_SESSION['user']['role'] !== 'team_leader') {
            jsonResponse(['success' => false, 'message' => 'Team incentives are only available to Team Leaders.'], 403);
        }
        $sales = new SalesController(Database::connect());
        jsonResponse([
            'success' => true,
            'result' => $sales->teamIncentives((int) $_SESSION['user']['id']),
        ]);
    }

    if ($path === '/api/sales/team-rewards' && $method === 'GET') {
        requireAuthenticated();
        if ($_SESSION['user']['role'] !== 'team_leader') {
            jsonResponse(['success' => false, 'message' => 'Team rewards are only available to Team Leaders.'], 403);
        }
        $sales = new SalesController(Database::connect());
        jsonResponse([
            'success' => true,
            'result' => $sales->teamTargetRewards(
                (int) $_SESSION['user']['id'],
                (string) ($_GET['month'] ?? AppTime::now()->format('Y-m'))
            ),
        ]);
    }

    if ($path === '/api/sales/team-grant-incentives' && $method === 'GET') {
        requireAuthenticated();
        if ($_SESSION['user']['role'] !== 'team_leader') {
            jsonResponse(['success' => false, 'message' => 'Team grant incentives are only available to Team Leaders.'], 403);
        }
        $sales = new SalesController(Database::connect());
        jsonResponse([
            'success' => true,
            'result' => $sales->teamGrantIncentives(
                (int) $_SESSION['user']['id'],
                (string) ($_GET['month'] ?? AppTime::now()->format('Y-m'))
            ),
        ]);
    }

    if ($path === '/api/sales/incentives/earned' && $method === 'GET') {
        requireSubEditor();
        $sales = new SalesController(Database::connect());
        jsonResponse([
            'success' => true,
            'result' => $sales->subEditorEarnedIncentives(),
        ]);
    }

    if ($path === '/api/sales/daily' && $method === 'POST') {
        requireAuthenticated();
        $sales = new SalesController(Database::connect());
        jsonResponse([
            'success' => true,
            'sale' => $sales->saveDailySales(
                (int) $_SESSION['user']['id'],
                (string) $_SESSION['user']['role'],
                parseJsonBody()
            ),
        ]);
    }

    if ($path === '/api/sales/unlock-otp/send' && $method === 'POST') {
        requireSalesEmployee();
        (new UnlockOtpController(Database::connect()))->send((int) $_SESSION['user']['id']);
        jsonResponse(['success' => true, 'message' => 'A verification code has been sent to your employee email.']);
    }

    if ($path === '/api/sales/unlock-otp/verify' && $method === 'POST') {
        requireSalesEmployee();
        (new UnlockOtpController(Database::connect()))->verify(
            (int) $_SESSION['user']['id'],
            parseJsonBody()
        );
        jsonResponse(['success' => true, 'message' => 'Email verification complete.']);
    }

    if (is_string($path) && preg_match('#^/api/team-assignments/([1-9][0-9]*)/available-telecallers$#', $path, $matches) && $method === 'GET') {
        requireSubEditor();
        $assignments = new TeamAssignmentController(Database::connect());
        jsonResponse([
            'success' => true,
            'telecallers' => $assignments->listAvailableTelecallers((int) $matches[1]),
        ]);
    }

    if ($path === '/api/team-assignments/history' && $method === 'GET') {
        requireSubEditor();
        $assignments = new TeamAssignmentController(Database::connect());
        jsonResponse(['success' => true, 'history' => $assignments->listAssignmentHistory()]);
    }

    if (is_string($path) && preg_match('#^/api/team-assignments/([1-9][0-9]*)/telecallers$#', $path, $matches) && $method === 'GET') {
        requireSubEditor();
        $assignments = new TeamAssignmentController(Database::connect());
        jsonResponse([
            'success' => true,
            'telecallers' => $assignments->listAssignedTelecallers((int) $matches[1]),
        ]);
    }

    if ($path === '/api/team-assignments' && $method === 'POST') {
        requireSubEditor();
        $payload = parseJsonBody();
        $assignments = new TeamAssignmentController(Database::connect());
        $assignments->assignTelecaller(
            (int) ($payload['teamLeaderId'] ?? 0),
            (int) ($payload['telecallerId'] ?? 0),
            (int) $_SESSION['user']['id']
        );
        jsonResponse(['success' => true, 'message' => 'Telecaller assigned to the Team Leader.'], 201);
    }

    if ($path === '/api/team-leader/team' && $method === 'GET') {
        requireAuthenticated();
        if ($_SESSION['user']['role'] !== 'team_leader') {
            jsonResponse(['success' => false, 'message' => 'Only Team Leaders can view their assigned team.'], 403);
        }

        $team = (new TeamAssignmentController(Database::connect()))->teamDashboard((int) $_SESSION['user']['id']);
        jsonResponse(['success' => true, 'team' => $team]);
    }

    if (is_string($path) && preg_match('#^/api/employees/([1-9][0-9]*)/credentials/resend$#', $path, $matches) && $method === 'POST') {
        requireSubEditor();
        $delivery = (new EmployeeController(Database::connect()))->resendCredentials((int) $matches[1]);
        jsonResponse(['success' => true, 'emailDelivery' => $delivery]);
    }

    if ($path === '/api/activity/recent' && $method === 'GET') {
        requireSubEditor();
        $activities = (new EmployeeController(Database::connect()))->listRecentActivity();
        jsonResponse(['success' => true, 'activities' => $activities]);
    }

    if ($path === '/api/dashboard/summary' && $method === 'GET') {
        requireAuthenticated();
        jsonResponse((new DashboardController(Database::connect()))->summary($_SESSION['user']['role']));
    }

    jsonResponse([
        'success' => false,
        'message' => 'Route not found.',
    ], 404);
} catch (InvalidArgumentException $error) {
    jsonResponse(['success' => false, 'message' => $error->getMessage()], 422);
} catch (DomainException $error) {
    jsonResponse(['success' => false, 'message' => $error->getMessage()], 409);
} catch (RuntimeException $error) {
    error_log($error->getMessage());
    jsonResponse(['success' => false, 'message' => $error->getMessage()], 503);
} catch (Throwable $error) {
    error_log((string) $error);
    jsonResponse(['success' => false, 'message' => 'The request could not be completed. Check the backend logs.'], 500);
}

use Arowin\RouteAuthorization;

function requireAuthenticated(): void
{
    $message = RouteAuthorization::ensureAuthenticated($_SESSION ?? []);
    if ($message !== null) {
        jsonResponse(['success' => false, 'message' => $message], 401);
    }
}

function requireSubEditor(): void
{
    $message = RouteAuthorization::ensureSubEditor($_SESSION ?? []);
    if ($message !== null) {
        jsonResponse(['success' => false, 'message' => $message], 403);
    }
}

function requireAttendanceEmployee(): void
{
    $message = RouteAuthorization::ensureAttendanceEmployee($_SESSION ?? []);
    if ($message !== null) {
        jsonResponse(['success' => false, 'message' => $message], 403);
    }
}

function requireLeaveApplicant(): int
{
    $message = RouteAuthorization::ensureLeaveApplicant($_SESSION ?? []);
    if ($message !== null) {
        jsonResponse(['success' => false, 'message' => $message], 403);
    }

    return (int) ($_SESSION['user']['id'] ?? 0);
}

function requireKycEmployee(): int
{
    $message = RouteAuthorization::ensureKycEmployee($_SESSION ?? []);
    if ($message !== null) {
        jsonResponse(['success' => false, 'message' => $message], 403);
    }

    return (int) ($_SESSION['user']['id'] ?? 0);
}

function requireDailyActivityEmployee(): int
{
    $message = RouteAuthorization::ensureDailyActivityEmployee($_SESSION ?? []);
    if ($message !== null) {
        jsonResponse(['success' => false, 'message' => $message], 403);
    }

    return (int) ($_SESSION['user']['id'] ?? 0);
}

function requireSalesEmployee(): void
{
    $message = RouteAuthorization::ensureSalesEmployee($_SESSION ?? []);
    if ($message !== null) {
        jsonResponse(['success' => false, 'message' => $message], 403);
    }
}

function requireSupportEmployee(): int
{
    $message = RouteAuthorization::ensureSupportEmployee($_SESSION ?? []);
    if ($message !== null) {
        jsonResponse(['success' => false, 'message' => $message], 403);
    }

    return (int) ($_SESSION['user']['id'] ?? 0);
}
