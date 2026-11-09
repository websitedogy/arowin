<?php

declare(strict_types=1);

namespace Arowin\Modules\Attendance;

use Arowin\Shared\AppTime;
use DateTimeImmutable;
use DomainException;
use InvalidArgumentException;
use PDO;
use PDOException;
use RuntimeException;

final class AttendanceController
{
    private const OFFICE_RADIUS_METERS = 200;
    private const CHECKIN_FROM = '00:00:00';
    private const CHECKIN_UNTIL = '10:30:00';
    private const LATE_AFTER = '09:45:59';
    private const CHECKOUT_FROM = '17:30:00';
    private const LATE_DEDUCTION = 100.00;
    private const MAX_SELFIE_BYTES = 4_000_000;

    public function __construct(
        private readonly PDO $database,
        private readonly ?DateTimeImmutable $nowOverride = null,
    ) {
    }

    public function officeLocation(): ?array
    {
        $row = $this->database->query(
            'SELECT address, latitude, longitude, updated_at
             FROM office_locations WHERE id = 1'
        )->fetch();

        if (!is_array($row)) {
            return null;
        }

        return [
            'address' => (string) $row['address'],
            'latitude' => (float) $row['latitude'],
            'longitude' => (float) $row['longitude'],
            'updatedAt' => $row['updated_at'],
            'radiusMeters' => self::OFFICE_RADIUS_METERS,
        ];
    }

    public function listHolidays(): array
    {
        return $this->database->query(
            'SELECT holiday_date AS date, name
             FROM attendance_holidays
             ORDER BY holiday_date ASC'
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    public function saveHoliday(array $payload, int $configuredBy): array
    {
        $date = trim((string) ($payload['date'] ?? ''));
        $name = trim((string) ($payload['name'] ?? ''));
        $parsedDate = DateTimeImmutable::createFromFormat('!Y-m-d', $date, AppTime::timeZone());
        if ($parsedDate === false || $parsedDate->format('Y-m-d') !== $date) {
            throw new InvalidArgumentException('Choose a valid holiday date.');
        }
        if ($name === '' || preg_match('/^.{1,120}$/us', $name) !== 1) {
            throw new InvalidArgumentException('Enter a holiday name up to 120 characters.');
        }

        $statement = $this->database->prepare(
            'INSERT INTO attendance_holidays (holiday_date, name, configured_by)
             VALUES (:holiday_date, :name, :configured_by)
             ON DUPLICATE KEY UPDATE name = VALUES(name), configured_by = VALUES(configured_by)'
        );
        $statement->execute([
            'holiday_date' => $date,
            'name' => $name,
            'configured_by' => $configuredBy,
        ]);

        return ['date' => $date, 'name' => $name];
    }

    public function deleteHoliday(string $date): void
    {
        $parsedDate = DateTimeImmutable::createFromFormat('!Y-m-d', $date, AppTime::timeZone());
        if ($parsedDate === false || $parsedDate->format('Y-m-d') !== $date) {
            throw new InvalidArgumentException('Choose a valid holiday date.');
        }

        $statement = $this->database->prepare('DELETE FROM attendance_holidays WHERE holiday_date = :holiday_date');
        $statement->execute(['holiday_date' => $date]);
    }

    public function currentAttendance(int $userId): array
    {
        $employee = $this->attendanceEmployee($userId);

        $now = $this->now();
        $today = $now->format('Y-m-d');
        $salary = $employee['salary_amount'] === null ? null : (float) $employee['salary_amount'];
        $joiningDate = $employee['joining_date'];

        $todayQuery = $this->database->prepare(
            'SELECT check_in_at, check_out_at, late_deduction, on_time_bonus, check_in_distance_m, check_out_distance_m
             FROM attendance_records WHERE user_id = :user_id AND attendance_date = :attendance_date'
        );
        $todayQuery->execute(['user_id' => $userId, 'attendance_date' => $today]);
        $todayRecord = $todayQuery->fetch();

        $monthDetails = $this->monthDetails($userId, $now->format('Y-m'), $salary, $joiningDate);
        $todayDetails = $monthDetails['days'][(int) $now->format('j') - 1];
        $location = $this->officeLocation();

        return [
            'salaryConfigured' => $salary !== null,
            'joiningDate' => $joiningDate,
            'monthlySalary' => $salary,
            'dailySalary' => $monthDetails['dailySalary'],
            'earned' => $monthDetails['earned'],
            'activityBonus' => $monthDetails['activityBonus'],
            'onTimeBonus' => $monthDetails['onTimeBonus'],
            'onTimeBonusAmount' => $this->onTimeBonusSettingValue(),
            'leaveDeductions' => $monthDetails['leaveDeductions'],
            'lateDeductions' => $monthDetails['lateDeductions'],
            'remainingPayable' => $monthDetails['remainingPayable'],
            'attendedDays' => $monthDetails['attendedDays'],
            'month' => $now->format('Y-m'),
            'today' => is_array($todayRecord) ? [
                'checkedInAt' => $todayRecord['check_in_at'],
                'checkedOutAt' => $todayRecord['check_out_at'],
                'lateDeduction' => (float) $todayRecord['late_deduction'],
                'onTimeBonus' => (float) ($todayRecord['on_time_bonus'] ?? 0),
                'checkInDistanceMeters' => $todayRecord['check_in_distance_m'] === null ? null : (float) $todayRecord['check_in_distance_m'],
                'checkOutDistanceMeters' => $todayRecord['check_out_distance_m'] === null ? null : (float) $todayRecord['check_out_distance_m'],
            ] : null,
            'todayIsHoliday' => $todayDetails['status'] === 'H',
            'todayHolidayName' => $todayDetails['holidayName'],
            'todayIsApprovedLeave' => $todayDetails['status'] === 'leave',
            'todayIsSunday' => $todayDetails['isSunday'],
            'officeConfigured' => $location !== null,
            'officeAddress' => $location['address'] ?? null,
            'officeRadiusMeters' => self::OFFICE_RADIUS_METERS,
            'checkInFrom' => self::CHECKIN_FROM,
            'checkInUntil' => self::CHECKIN_UNTIL,
            'lateAfter' => self::LATE_AFTER,
            'checkoutFrom' => self::CHECKOUT_FROM,
            'lateDeductionAmount' => self::LATE_DEDUCTION,
            'serverTime' => $now->format(DATE_ATOM),
        ];
    }

    public function settings(): array
    {
        $row = $this->database->query(
            'SELECT on_time_bonus_amount FROM attendance_settings WHERE id = 1'
        )->fetch(PDO::FETCH_ASSOC);

        return [
            'onTimeBonusAmount' => $row === false ? 0.0 : (float) $row['on_time_bonus_amount'],
        ];
    }

    public function saveOnTimeBonusAmount(array $payload, int $configuredBy): array
    {
        $rawAmount = $payload['onTimeBonusAmount'] ?? $payload['amount'] ?? null;
        if (!is_numeric($rawAmount) || !is_finite((float) $rawAmount)) {
            throw new InvalidArgumentException('Enter a valid on-time attendance bonus amount.');
        }

        $amount = round((float) $rawAmount, 2);
        if ($amount < 0 || $amount > 99999999.99) {
            throw new InvalidArgumentException('The on-time attendance bonus must be between 0 and 99999999.99.');
        }

        $statement = $this->database->prepare(
            'INSERT INTO attendance_settings (id, on_time_bonus_amount, configured_by)
             VALUES (1, :amount, :configured_by)
             ON DUPLICATE KEY UPDATE
                on_time_bonus_amount = VALUES(on_time_bonus_amount),
                configured_by = VALUES(configured_by),
                updated_at = CURRENT_TIMESTAMP'
        );
        $statement->execute([
            'amount' => $amount,
            'configured_by' => $configuredBy,
        ]);

        return ['onTimeBonusAmount' => $amount];
    }

    public function history(int $userId, string $month): array
    {
        if (preg_match('/^[0-9]{4}-(0[1-9]|1[0-2])$/', $month) !== 1) {
            throw new InvalidArgumentException('Choose a valid attendance month.');
        }

        $employee = $this->attendanceEmployee($userId);

        $salary = $employee['salary_amount'] === null ? null : (float) $employee['salary_amount'];
        $joiningDate = $employee['joining_date'];

        return $this->monthDetails($userId, $month, $salary, $joiningDate);
    }

    public function checkIn(int $userId, array $payload): array
    {
        $now = $this->now();
        $time = $now->format('H:i:s');
        if ($time < self::CHECKIN_FROM) {
            throw new DomainException('Check-in opens at 12:00 AM.');
        }
        if ($time >= self::CHECKIN_UNTIL) {
            throw new DomainException('Check-in is closed at 10:30 AM.');
        }

        $date = $now->format('Y-m-d');
        $onTimeBonus = $this->isOnTimeCheckIn($now) ? $this->onTimeBonusSettingValue() : 0.0;
        $this->assertWorkingDay($date);
        $this->assertNotOnApprovedLeave($userId, $date);
        $this->assertEmployeeHasSalary($userId, $date);
        $location = $this->validateOfficeDistance($payload);
        $selfiePath = $this->saveSelfie($payload['selfie'] ?? null, $userId, $date, 'in');

        try {
            $late = $now->format('H:i:s') > self::LATE_AFTER;
            $statement = $this->database->prepare(
                'INSERT INTO attendance_records
                    (user_id, attendance_date, check_in_at, check_in_latitude, check_in_longitude,
                     check_in_distance_m, check_in_selfie, late_deduction, on_time_bonus)
                 VALUES
                    (:user_id, :attendance_date, :check_in_at, :latitude, :longitude,
                     :distance, :selfie, :late_deduction, :on_time_bonus)'
            );
            $statement->execute([
                'user_id' => $userId,
                'attendance_date' => $date,
                'check_in_at' => $now->format('Y-m-d H:i:s'),
                'latitude' => (float) $payload['latitude'],
                'longitude' => (float) $payload['longitude'],
                'distance' => $location['distance'],
                'selfie' => $selfiePath,
                'late_deduction' => $late ? self::LATE_DEDUCTION : 0,
                'on_time_bonus' => $onTimeBonus,
            ]);
        } catch (PDOException $error) {
            $this->deleteSelfie($selfiePath);
            if ($error->getCode() === '23000') {
                throw new DomainException('Today’s attendance has already been checked in.');
            }
            throw $error;
        } catch (\Throwable $error) {
            $this->deleteSelfie($selfiePath);
            throw $error;
        }

        return $this->currentAttendance($userId);
    }

    public function verifyLocation(array $payload): array
    {
        $location = $this->validateOfficeDistance($payload);

        return [
            'withinOfficeRadius' => true,
            'distanceMeters' => $location['distance'],
            'radiusMeters' => self::OFFICE_RADIUS_METERS,
        ];
    }

    public function checkOut(int $userId, array $payload): array
    {
        $now = $this->now();
        if ($now->format('H:i:s') < self::CHECKOUT_FROM) {
            throw new DomainException('Check-out opens at 5:30 PM.');
        }

        $date = $now->format('Y-m-d');
        $this->assertWorkingDay($date);
        $location = $this->validateOfficeDistance($payload);
        $selfiePath = $this->saveSelfie($payload['selfie'] ?? null, $userId, $date, 'out');
        try {
            $this->database->beginTransaction();
            $record = $this->database->prepare(
                'SELECT check_in_at, check_out_at FROM attendance_records
                 WHERE user_id = :user_id AND attendance_date = :attendance_date FOR UPDATE'
            );
            $record->execute(['user_id' => $userId, 'attendance_date' => $date]);
            $today = $record->fetch();
            if (!is_array($today)) {
                throw new DomainException('Check in first before checking out.');
            }
            if ($today['check_out_at'] !== null) {
                throw new DomainException('Today’s attendance has already been checked out.');
            }

            $update = $this->database->prepare(
                'UPDATE attendance_records
                 SET check_out_at = :check_out_at,
                     check_out_latitude = :latitude,
                     check_out_longitude = :longitude,
                     check_out_distance_m = :distance,
                     check_out_selfie = :selfie
                 WHERE user_id = :user_id AND attendance_date = :attendance_date'
            );
            $update->execute([
                'check_out_at' => $now->format('Y-m-d H:i:s'),
                'latitude' => (float) $payload['latitude'],
                'longitude' => (float) $payload['longitude'],
                'distance' => $location['distance'],
                'selfie' => $selfiePath,
                'user_id' => $userId,
                'attendance_date' => $date,
            ]);
            $this->database->commit();
        } catch (\Throwable $error) {
            if ($this->database->inTransaction()) {
                $this->database->rollBack();
            }
            $this->deleteSelfie($selfiePath);
            throw $error;
        }

        return $this->currentAttendance($userId);
    }

    private function monthDetails(int $userId, string $month, ?float $salary, ?string $joiningDate): array
    {
        $timeZone = AppTime::timeZone();
        $monthStart = DateTimeImmutable::createFromFormat('!Y-m-d', $month . '-01', $timeZone);
        if ($monthStart === false) {
            throw new InvalidArgumentException('Choose a valid attendance month.');
        }

        $monthEnd = $monthStart->modify('last day of this month');
        $recordsQuery = $this->database->prepare(
            'SELECT attendance_date, check_in_at, check_out_at, late_deduction, on_time_bonus
             FROM attendance_records
             WHERE user_id = :user_id
               AND attendance_date >= :month_start
               AND attendance_date <= :month_end'
        );
        $recordsQuery->execute([
            'user_id' => $userId,
            'month_start' => $monthStart->format('Y-m-d'),
            'month_end' => $monthEnd->format('Y-m-d'),
        ]);
        $records = [];
        foreach ($recordsQuery->fetchAll(PDO::FETCH_ASSOC) as $record) {
            $records[$record['attendance_date']] = $record;
        }

        $holidaysQuery = $this->database->prepare(
            'SELECT holiday_date, name
             FROM attendance_holidays
             WHERE holiday_date >= :month_start AND holiday_date <= :month_end'
        );
        $holidaysQuery->execute([
            'month_start' => $monthStart->format('Y-m-d'),
            'month_end' => $monthEnd->format('Y-m-d'),
        ]);
        $holidays = [];
        foreach ($holidaysQuery->fetchAll(PDO::FETCH_ASSOC) as $holiday) {
            $holidays[$holiday['holiday_date']] = (string) $holiday['name'];
        }

        $nextMonthStart = $monthEnd->modify('+1 day');
        $activityBonusQuery = $this->database->prepare(
            "SELECT DATE(a.submitted_at) AS activity_date, a.platform, a.bonus_amount,
                    COUNT(s.id) AS screenshot_count
             FROM daily_activities a
             LEFT JOIN daily_activity_screenshots s ON s.activity_id = a.id
             WHERE a.user_id = :user_id
               AND a.status = 'verified'
               AND a.submitted_at >= :month_start
               AND a.submitted_at < :next_month_start
             GROUP BY a.id
             ORDER BY a.submitted_at ASC, a.id ASC"
        );
        $activityBonusQuery->execute([
            'user_id' => $userId,
            'month_start' => $monthStart->format('Y-m-d'),
            'next_month_start' => $nextMonthStart->format('Y-m-d'),
        ]);
        $dailyActivityBonuses = [];
        $legacySocialBonuses = [];
        foreach ($activityBonusQuery->fetchAll(PDO::FETCH_ASSOC) as $activityBonusRow) {
            $activityDate = (string) $activityBonusRow['activity_date'];
            if ($activityBonusRow['bonus_amount'] !== null) {
                $dailyActivityBonuses[$activityDate] = ($dailyActivityBonuses[$activityDate] ?? 0.0)
                    + (float) $activityBonusRow['bonus_amount'];
            } elseif (in_array($activityBonusRow['platform'], ['Instagram', 'Facebook', 'WhatsApp Status'], true)) {
                $legacySocialBonuses[$activityDate] = min(
                    6.0,
                    ($legacySocialBonuses[$activityDate] ?? 0.0) + (int) $activityBonusRow['screenshot_count'] * 2.0
                );
            }
        }
        foreach ($legacySocialBonuses as $activityDate => $legacyBonus) {
            $dailyActivityBonuses[$activityDate] = ($dailyActivityBonuses[$activityDate] ?? 0.0) + $legacyBonus;
        }

        $leaveQuery = $this->database->prepare(
            "SELECT start_date, end_date
             FROM leave_requests
             WHERE user_id = :user_id
               AND status = 'approved'
               AND start_date <= :month_end
               AND end_date >= :month_start"
        );
        $leaveQuery->execute([
            'user_id' => $userId,
            'month_start' => $monthStart->format('Y-m-d'),
            'month_end' => $monthEnd->format('Y-m-d'),
        ]);
        $approvedLeaveDays = [];
        foreach ($leaveQuery->fetchAll(PDO::FETCH_ASSOC) as $leave) {
            $leaveStart = new DateTimeImmutable(max($leave['start_date'], $monthStart->format('Y-m-d')), $timeZone);
            $leaveEnd = new DateTimeImmutable(min($leave['end_date'], $monthEnd->format('Y-m-d')), $timeZone);
            for ($leaveDate = $leaveStart; $leaveDate <= $leaveEnd; $leaveDate = $leaveDate->modify('+1 day')) {
                $approvedLeaveDays[$leaveDate->format('Y-m-d')] = true;
            }
        }

        $now = $this->now();
        $today = $now->format('Y-m-d');
        $dailySalary = $salary === null ? null : $salary / (int) $monthEnd->format('d');
        $earnedTotal = 0.0;
        $activityBonusTotal = 0.0;
        $onTimeBonusTotal = 0.0;
        $leaveDeductions = 0.0;
        $lateDeductions = 0.0;
        $attendedDays = 0;
        $days = [];

        for ($date = $monthStart; $date <= $monthEnd; $date = $date->modify('+1 day')) {
            $dateString = $date->format('Y-m-d');
            $record = $records[$dateString] ?? null;
            $isSunday = $date->format('w') === '0';
            $holidayName = $holidays[$dateString] ?? null;
            $isHoliday = $isSunday || $holidayName !== null;
            $beforeJoining = is_string($joiningDate) && $dateString < $joiningDate;
            $isFuture = $dateString > $today;
            $isComplete = is_array($record)
                && $record['check_in_at'] !== null
                && $record['check_out_at'] !== null;

            if ($isHoliday) {
                $status = 'H';
            } elseif ($beforeJoining) {
                $status = 'not-joined';
            } elseif ($isComplete) {
                $status = 'present';
            } elseif (isset($approvedLeaveDays[$dateString])) {
                $status = 'leave';
            } elseif ($isFuture) {
                $status = 'future';
            } elseif (is_array($record)) {
                $status = 'incomplete';
            } elseif ($dateString === $today && $now->format('H:i:s') < self::CHECKIN_UNTIL) {
                $status = 'pending';
            } else {
                $status = 'absent';
            }

            $isPaidDay = !$beforeJoining && !$isFuture && ($isHoliday || $isComplete);
            $dailyEarned = $salary === null ? null : ($isPaidDay ? round($dailySalary, 2) : 0.0);
            if ($isPaidDay && $dailySalary !== null) {
                $earnedTotal += $dailySalary;
            }
            $dailyActivityBonus = !$beforeJoining && !$isFuture
                ? (float) ($dailyActivityBonuses[$dateString] ?? 0)
                : 0.0;
            $activityBonusTotal += $dailyActivityBonus;
            $dailyOnTimeBonus = !$beforeJoining && !$isFuture && !$isHoliday && is_array($record)
                ? (float) ($record['on_time_bonus'] ?? 0)
                : 0.0;
            if ($dailyOnTimeBonus > 0) {
                $onTimeBonusTotal += $dailyOnTimeBonus;
            }
            if (isset($approvedLeaveDays[$dateString]) && !$isHoliday && !$beforeJoining && !$isFuture && !$isComplete && $dailySalary !== null) {
                $leaveDeductions += $dailySalary;
            }
            $lateDeduction = is_array($record) ? (float) $record['late_deduction'] : 0.0;
            if (!$beforeJoining && !$isFuture) {
                $lateDeductions += $lateDeduction;
            }
            if ($isComplete && !$isHoliday && !$beforeJoining && !$isFuture) {
                $attendedDays++;
            }

            $days[] = [
                'date' => $dateString,
                'status' => $status,
                'isSunday' => $isSunday,
                'holidayName' => $holidayName,
                'checkInAt' => is_array($record) ? $record['check_in_at'] : null,
                'checkOutAt' => is_array($record) ? $record['check_out_at'] : null,
                'lateDeduction' => $lateDeduction,
                'onTimeBonus' => $dailyOnTimeBonus,
                'earned' => $dailyEarned,
                'activityBonus' => $dailyActivityBonus,
            ];
        }

        $earned = $salary === null ? null : min($salary, round($earnedTotal, 2));
        $activityBonusTotal = round($activityBonusTotal, 2);
        $onTimeBonusTotal = round($onTimeBonusTotal, 2);
        $lateDeductions = round($lateDeductions, 2);

        return [
            'month' => $month,
            'monthlySalary' => $salary,
            'dailySalary' => $dailySalary,
            'earned' => $earned,
            'activityBonus' => $activityBonusTotal,
            'onTimeBonus' => $onTimeBonusTotal,
            'leaveDeductions' => $salary === null ? null : round($leaveDeductions, 2),
            'lateDeductions' => $lateDeductions,
            'remainingPayable' => $earned === null
                ? null
                : max(0, round($earned + $activityBonusTotal + $onTimeBonusTotal - $lateDeductions, 2)),
            'attendedDays' => $attendedDays,
            'days' => $days,
        ];
    }
    private function onTimeBonusSettingValue(): float
    {
        $row = $this->database->query(
            'SELECT on_time_bonus_amount FROM attendance_settings WHERE id = 1'
        )->fetch(PDO::FETCH_ASSOC);

        return $row === false ? 0.0 : (float) $row['on_time_bonus_amount'];
    }

    private function isOnTimeCheckIn(DateTimeImmutable $moment): bool
    {
        return $moment->format('H:i:s') <= '09:30:00';
    }

    private function assertWorkingDay(string $date): void
    {
        $day = new DateTimeImmutable($date, AppTime::timeZone());
        if ($day->format('w') === '0') {
            throw new DomainException('Attendance is not required on Sundays. Sunday is a paid holiday.');
        }

        $statement = $this->database->prepare(
            'SELECT name FROM attendance_holidays WHERE holiday_date = :holiday_date'
        );
        $statement->execute(['holiday_date' => $date]);
        $holidayName = $statement->fetchColumn();
        if (is_string($holidayName)) {
            throw new DomainException(sprintf('Attendance is not required on %s. This is a paid holiday.', $holidayName));
        }
    }

    private function assertNotOnApprovedLeave(int $userId, string $date): void
    {
        $statement = $this->database->prepare(
            "SELECT id FROM leave_requests
             WHERE user_id = :user_id
               AND status = 'approved'
               AND start_date <= :start_date
               AND end_date >= :end_date
             LIMIT 1"
        );
        $statement->execute([
            'user_id' => $userId,
            'start_date' => $date,
            'end_date' => $date,
        ]);
        if ($statement->fetchColumn() !== false) {
            throw new DomainException('You have approved leave today. Attendance cannot be marked for this date.');
        }
    }

    private function validateOfficeDistance(array $payload): array
    {
        $latitude = $payload['latitude'] ?? null;
        $longitude = $payload['longitude'] ?? null;
        if (!is_numeric($latitude) || !is_numeric($longitude)
            || (float) $latitude < -90 || (float) $latitude > 90
            || (float) $longitude < -180 || (float) $longitude > 180) {
            throw new InvalidArgumentException('Allow location access and try again.');
        }

        $office = $this->officeLocation();
        if ($office === null) {
            throw new DomainException('Office location has not been configured by the Sub Editor.');
        }

        $distance = $this->distanceMeters(
            (float) $latitude,
            (float) $longitude,
            $office['latitude'],
            $office['longitude']
        );
        if ($distance > self::OFFICE_RADIUS_METERS) {
            throw new DomainException(sprintf(
                'You are %.0f m from the office. Attendance is available only within %d m.',
                $distance,
                self::OFFICE_RADIUS_METERS
            ));
        }

        return ['distance' => round($distance, 2)];
    }

    private function assertEmployeeHasSalary(int $userId, string $date): void
    {
        $employee = $this->attendanceEmployee($userId);
        if ($employee['salary_amount'] === null) {
            throw new DomainException('Ask your Sub Editor to set your joining date and salary before marking attendance.');
        }
        if (is_string($employee['joining_date']) && $date < $employee['joining_date']) {
            throw new DomainException('Attendance cannot be marked before your joining date.');
        }
    }

    private function attendanceEmployee(int $userId): array
    {
        $statement = $this->database->prepare(
            'SELECT salary.joining_date, salary.salary_amount
             FROM users employee
             LEFT JOIN employee_salaries salary ON salary.user_id = employee.id
             WHERE employee.id = :id AND employee.role IN (\'team_leader\', \'telecaller\') AND employee.is_active = 1'
        );
        $statement->execute(['id' => $userId]);
        $employee = $statement->fetch();
        if (!is_array($employee)) {
            throw new DomainException('Employee account is not available.');
        }

        return $employee;
    }

    private function saveSelfie(mixed $dataUrl, int $userId, string $date, string $kind): string
    {
        if (!is_string($dataUrl)
            || !preg_match('#^data:image/(jpeg|png|webp);base64,([A-Za-z0-9+/]+={0,2})$#', $dataUrl, $matches)) {
            throw new InvalidArgumentException('Capture a valid attendance selfie to continue.');
        }

        $image = base64_decode($matches[2], true);
        if ($image === false || strlen($image) > self::MAX_SELFIE_BYTES || strlen($image) < 100) {
            throw new InvalidArgumentException('The selfie must be a valid image smaller than 4 MB.');
        }
        $imageInfo = getimagesizefromstring($image);
        $mimeTypes = ['jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp'];
        if (!is_array($imageInfo) || ($imageInfo['mime'] ?? '') !== $mimeTypes[$matches[1]]) {
            throw new InvalidArgumentException('The captured selfie is not a supported image.');
        }

        $directory = dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'attendance-selfies';
        if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
            throw new RuntimeException('Attendance selfie storage could not be created.');
        }

        $filename = sprintf('%d-%s-%s-%s.%s', $userId, $date, $kind, bin2hex(random_bytes(16)), $matches[1]);
        $absolutePath = $directory . DIRECTORY_SEPARATOR . $filename;
        $written = file_put_contents($absolutePath, $image, LOCK_EX);
        if ($written !== strlen($image)) {
            if (is_file($absolutePath) && !unlink($absolutePath)) {
                error_log('Could not remove a partial attendance selfie file.');
            }
            throw new RuntimeException('Attendance selfie could not be saved.');
        }
        if (DIRECTORY_SEPARATOR !== '\\' && !chmod($absolutePath, 0600)) {
            if (!unlink($absolutePath)) {
                error_log('Could not remove an attendance selfie with unsafe file permissions.');
            }
            throw new RuntimeException('Attendance selfie permissions could not be secured.');
        }

        return $absolutePath;
    }

    private function deleteSelfie(string $path): void
    {
        if (is_file($path) && !unlink($path)) {
            error_log('Could not remove an unused attendance selfie file.');
        }
    }

    private function distanceMeters(float $latitudeA, float $longitudeA, float $latitudeB, float $longitudeB): float
    {
        $earthRadius = 6371000;
        $latitudeDelta = deg2rad($latitudeB - $latitudeA);
        $longitudeDelta = deg2rad($longitudeB - $longitudeA);
        $a = sin($latitudeDelta / 2) ** 2
            + cos(deg2rad($latitudeA)) * cos(deg2rad($latitudeB)) * sin($longitudeDelta / 2) ** 2;

        return $earthRadius * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }

    private function now(): DateTimeImmutable
    {
        return $this->nowOverride ?? AppTime::now();
    }
}
