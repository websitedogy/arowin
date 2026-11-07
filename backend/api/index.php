<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use Arowin\Database;
use Arowin\Modules\Auth\AuthController;
use Arowin\Modules\Attendance\AttendanceController;
use Arowin\Modules\Budget\BudgetController;
use Arowin\Modules\Credentials\CredentialDeliveryService;
use Arowin\Modules\Dashboard\DashboardController;
use Arowin\Modules\Employees\EmployeeController;
use Arowin\Modules\TeamAssignments\TeamAssignmentController;

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
            : (new DateTimeImmutable('now', new DateTimeZone('Asia/Kolkata')))->format('Y-m');
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

function requireAuthenticated(): void
{
    if (!isset($_SESSION['user'])) {
        jsonResponse(['success' => false, 'message' => 'Sign in to continue.'], 401);
    }
}

function requireSubEditor(): void
{
    requireAuthenticated();

    if ($_SESSION['user']['role'] !== 'sub_editor') {
        jsonResponse(['success' => false, 'message' => 'Only Sub Editors can manage employee accounts.'], 403);
    }
}

function requireAttendanceEmployee(): void
{
    requireAuthenticated();

    if (!in_array($_SESSION['user']['role'], ['team_leader', 'telecaller'], true)) {
        jsonResponse(['success' => false, 'message' => 'Only Team Leaders and Telecallers can mark attendance.'], 403);
    }
}
