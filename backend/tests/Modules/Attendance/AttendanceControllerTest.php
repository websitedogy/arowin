<?php

declare(strict_types=1);

use Arowin\Modules\Attendance\AttendanceController;
use Arowin\Shared\AppTime;
use PHPUnit\Framework\TestCase;

final class AttendanceControllerTest extends TestCase
{
    private PDO $database;
    private int $userId = 1;

    protected function setUp(): void
    {
        $this->database = new SqliteCompatPdo();
        $this->database->exec('CREATE TABLE users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            employee_id TEXT,
            name TEXT,
            role TEXT,
            is_active INTEGER,
            email TEXT,
            password_hash TEXT
        )');
        $this->database->exec('CREATE TABLE employee_salaries (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER,
            joining_date TEXT,
            salary_amount REAL
        )');
        $this->database->exec('CREATE TABLE office_locations (
            id INTEGER PRIMARY KEY,
            address TEXT,
            latitude REAL,
            longitude REAL,
            updated_at TEXT
        )');
        $this->database->exec('CREATE TABLE attendance_holidays (
            holiday_date TEXT PRIMARY KEY,
            name TEXT
        )');
        $this->database->exec('CREATE TABLE attendance_records (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER,
            attendance_date TEXT,
            check_in_at TEXT,
            check_in_latitude REAL,
            check_in_longitude REAL,
            check_in_distance_m REAL,
            check_in_selfie TEXT,
            late_deduction REAL,
            on_time_bonus REAL DEFAULT 0,
            check_out_at TEXT,
            check_out_latitude REAL,
            check_out_longitude REAL,
            check_out_distance_m REAL,
            check_out_selfie TEXT,
            UNIQUE(user_id, attendance_date)
        )');
        $this->database->exec('CREATE TABLE attendance_settings (
            id INTEGER PRIMARY KEY,
            on_time_bonus_amount REAL NOT NULL DEFAULT 0,
            configured_by INTEGER,
            updated_at TEXT
        )');
        $this->database->exec("INSERT INTO attendance_settings (id, on_time_bonus_amount) VALUES (1, 250.00)");
        $this->database->exec('CREATE TABLE leave_requests (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER,
            leave_type TEXT,
            start_date TEXT,
            end_date TEXT,
            reason TEXT,
            status TEXT
        )');
        $this->database->exec('CREATE TABLE daily_activities (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER,
            platform TEXT,
            bonus_amount REAL,
            submitted_at TEXT,
            status TEXT,
            verified_by INTEGER,
            verified_at TEXT,
            reviewed_at TEXT,
            rejection_reason TEXT,
            created_at TEXT
        )');
        $this->database->exec('CREATE TABLE daily_activity_screenshots (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            activity_id INTEGER,
            file_name TEXT,
            original_name TEXT,
            mime_type TEXT,
            file_size INTEGER,
            created_at TEXT
        )');

        $this->database->exec("INSERT INTO users (id, employee_id, name, role, is_active, email, password_hash) VALUES (1, '123456', 'Team Leader', 'team_leader', 1, 'leader@example.com', 'hash')");
        $this->database->exec("INSERT INTO employee_salaries (user_id, joining_date, salary_amount) VALUES (1, '2024-01-01', 30000.00)");
        $this->database->exec("INSERT INTO office_locations (id, address, latitude, longitude) VALUES (1, 'Office', 17.2589440, 80.1392630)");
    }

    public function testCheckInSucceedsWhenEmployeeIsWithinOfficeRadius(): void
    {
        $now = $this->workdayMorning()->setTime(9, 30, 0);
        $controller = new AttendanceController($this->database, $now);
        $selfie = $this->validSelfieDataUrl();

        $result = $controller->checkIn($this->userId, [
            'latitude' => 17.2589440,
            'longitude' => 80.1392630,
            'selfie' => $selfie,
        ]);

        $this->assertArrayHasKey('today', $result);
        $this->assertNotNull($result['today']);
        $this->assertFalse($result['todayIsHoliday']);
        $this->assertNotNull($result['today']['checkedInAt']);
        $this->assertSame(0.0, $result['today']['checkInDistanceMeters']);
        $this->assertSame(250.0, $result['today']['onTimeBonus']);
        $this->assertSame(250.0, $result['onTimeBonus']);

        $this->database->exec('UPDATE attendance_settings SET on_time_bonus_amount = 500 WHERE id = 1');
        $history = $controller->history($this->userId, $now->format('Y-m'));
        $today = array_values(array_filter(
            $history['days'],
            static fn (array $day): bool => $day['date'] === $now->format('Y-m-d')
        ))[0];
        $this->assertSame(250.0, $today['onTimeBonus']);
        $this->assertSame(250.0, $history['onTimeBonus']);
    }

    public function testAfterCutoffCheckInDoesNotEarnOnTimeBonus(): void
    {
        $now = $this->workdayMorning()->setTime(9, 31, 0);
        $controller = new AttendanceController($this->database, $now);

        $result = $controller->checkIn($this->userId, [
            'latitude' => 17.2589440,
            'longitude' => 80.1392630,
            'selfie' => $this->validSelfieDataUrl(),
        ]);

        $this->assertSame(0.0, $result['today']['onTimeBonus']);
        $this->assertSame(0.0, $result['onTimeBonus']);
    }

    public function testDuplicateCheckInRaisesDomainException(): void
    {
        $now = $this->workdayMorning();
        $controller = new AttendanceController($this->database, $now);
        $date = $now->format('Y-m-d');
        $this->database->exec("INSERT INTO attendance_records (user_id, attendance_date, check_in_at, check_in_latitude, check_in_longitude, check_in_distance_m, check_in_selfie, late_deduction) VALUES (1, '$date', '$date 08:00:00', 17.2589440, 80.1392630, 0, 'unused.jpg', 0)");

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage("Today’s attendance has already been checked in.");

        $controller->checkIn($this->userId, [
            'latitude' => 17.2589440,
            'longitude' => 80.1392630,
            'selfie' => $this->validSelfieDataUrl(),
        ]);
    }

    public function testHistoryIncludesMonthlyAttendanceStatus(): void
    {
        $now = $this->workdayMorning();
        $controller = new AttendanceController($this->database, $now);
        $date = $now->format('Y-m-d');
        $this->database->exec("INSERT INTO attendance_records (user_id, attendance_date, check_in_at, check_in_latitude, check_in_longitude, check_in_distance_m, check_in_selfie, late_deduction, check_out_at) VALUES (1, '$date', '$date 08:00:00', 17.2589440, 80.1392630, 0, 'record.jpg', 0, '$date 17:30:00')");

        $result = $controller->history($this->userId, $now->format('Y-m'));

        $this->assertSame($now->format('Y-m'), $result['month']);
        $this->assertGreaterThanOrEqual(1, $result['attendedDays']);
        $this->assertCount(cal_days_in_month(CAL_GREGORIAN, (int) $now->format('n'), (int) $now->format('Y')), $result['days']);
    }

    public function testHistoryKeepsLegacyAndSavedActivityBonuses(): void
    {
        $now = $this->workdayMorning();
        $date = $now->format('Y-m-d');
        $this->database->exec(
            "INSERT INTO daily_activities (user_id, platform, submitted_at, status)
             VALUES (1, 'Instagram', '$date 08:00:00', 'verified')"
        );
        $legacyActivityId = (int) $this->database->lastInsertId();
        $this->database->exec(
            "INSERT INTO daily_activities (user_id, platform, bonus_amount, submitted_at, status)
             VALUES (1, 'YouTube', 7.5, '$date 09:00:00', 'verified')"
        );
        for ($index = 0; $index < 4; $index++) {
            $this->database->exec(
                "INSERT INTO daily_activity_screenshots (activity_id, file_name, original_name, mime_type, file_size)
                 VALUES ($legacyActivityId, 'legacy-$index.png', 'legacy-$index.png', 'image/png', 100)"
            );
        }

        $result = (new AttendanceController($this->database, $now))->history($this->userId, $now->format('Y-m'));

        $this->assertSame(13.5, $result['activityBonus']);
        $this->assertSame(
            max(0, round($result['earned'] + $result['activityBonus'] + $result['onTimeBonus'] - $result['lateDeductions'], 2)),
            $result['remainingPayable']
        );
    }

    public function testVerifyLocationRejectsCoordinatesOutsideOfficeRadius(): void
    {
        $controller = new AttendanceController($this->database);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('You are');

        $controller->verifyLocation([
            'latitude' => 17.3700000,
            'longitude' => 80.2600000,
        ]);
    }

    private function workdayMorning(): \DateTimeImmutable
    {
        $now = AppTime::now();
        $morning = $now->setTime(8, 30, 0);

        while ((int) $morning->format('N') >= 6) {
            $morning = $morning->modify('+1 day')->setTime(8, 30, 0);
        }

        return $morning;
    }

    private function validSelfieDataUrl(): string
    {
        if (!function_exists('imagecreate')) {
            self::fail('GD extension is required to generate a valid selfie test image.');
        }

        $image = imagecreatetruecolor(20, 20);
        $white = imagecolorallocate($image, 255, 255, 255);
        imagefill($image, 0, 0, $white);
        ob_start();
        imagepng($image);
        $png = (string) ob_get_clean();
        imagedestroy($image);

        return 'data:image/png;base64,' . base64_encode($png);
    }
}

final class SqliteCompatPdo extends PDO
{
    public function __construct()
    {
        parent::__construct('sqlite::memory:');
        $this->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->exec("ATTACH DATABASE ':memory:' AS information_schema");
        $this->exec('CREATE TABLE information_schema.COLUMNS (
            TABLE_SCHEMA TEXT,
            TABLE_NAME TEXT,
            COLUMN_NAME TEXT
        )');
        $this->exec("INSERT INTO information_schema.COLUMNS (TABLE_SCHEMA, TABLE_NAME, COLUMN_NAME) VALUES ('main', 'users', 'joining_date')");
    }

    public function prepare(string $query, array $driverOptions = []): PDOStatement
    {
        $query = preg_replace('/\s+FOR UPDATE\b/i', '', $query);

        return parent::prepare($query, $driverOptions);
    }

    public function query(string $query, ?int $fetchMode = null, mixed ...$params): PDOStatement|false
    {
        $query = preg_replace('/\s+FOR UPDATE\b/i', '', $query);
        $query = str_replace('DATABASE()', "'main'", $query);

        return parent::query($query, $fetchMode, ...$params);
    }
}
