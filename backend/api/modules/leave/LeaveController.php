<?php

declare(strict_types=1);

namespace Arowin\Modules\Leave;

use Arowin\Shared\AppTime;
use DateTimeImmutable;
use DomainException;
use InvalidArgumentException;
use PDO;
use Throwable;

final class LeaveController
{
    public function __construct(private readonly PDO $database)
    {
    }

    public function listRequests(?int $userId): array
    {
        $sql = 'SELECT lr.id, lr.user_id, u.employee_id, u.name AS employee_name, u.role,
                       lr.leave_type, lr.start_date, lr.end_date, lr.reason, lr.status, lr.created_at,
                       lr.reviewed_at, reviewer.name AS reviewer_name
                FROM leave_requests lr
                JOIN users u ON u.id = lr.user_id
                LEFT JOIN users reviewer ON reviewer.id = lr.reviewed_by';
        $parameters = [];
        if ($userId !== null) {
            $sql .= ' WHERE lr.user_id = :user_id';
            $parameters['user_id'] = $userId;
        }
        $sql .= ' ORDER BY CASE lr.status WHEN \'pending\' THEN 0 ELSE 1 END,
                          lr.created_at DESC, lr.id DESC';

        $statement = $this->database->prepare($sql);
        $statement->execute($parameters);

        return array_map(static fn (array $row): array => [
            'id' => (int) $row['id'],
            'employeeId' => (string) $row['employee_id'],
            'employeeName' => (string) $row['employee_name'],
            'role' => (string) $row['role'],
            'leaveType' => (string) $row['leave_type'],
            'startDate' => (string) $row['start_date'],
            'endDate' => (string) $row['end_date'],
            'reason' => (string) $row['reason'],
            'status' => (string) $row['status'],
            'createdAt' => (string) $row['created_at'],
            'reviewedAt' => $row['reviewed_at'],
            'reviewerName' => $row['reviewer_name'],
        ], $statement->fetchAll(PDO::FETCH_ASSOC));
    }

    public function apply(int $userId, array $payload): array
    {
        $leaveType = $payload['leaveType'] ?? null;
        if (!is_string($leaveType) || !in_array($leaveType, ['casual', 'sick', 'emergency', 'personal'], true)) {
            throw new InvalidArgumentException('Choose a valid leave type.');
        }

        $startDate = $this->validDate($payload['startDate'] ?? null, 'Choose a valid leave start date.');
        $endDate = $this->validDate($payload['endDate'] ?? null, 'Choose a valid leave end date.');
        if ($endDate < $startDate) {
            throw new InvalidArgumentException('The end date must be on or after the start date.');
        }
        if ($startDate < AppTime::now()->format('Y-m-d')) {
            throw new InvalidArgumentException('Leave cannot be requested for a past date.');
        }

        if (!is_string($payload['reason'] ?? null)) {
            throw new InvalidArgumentException('Enter a leave reason up to 500 characters.');
        }
        $reason = trim($payload['reason']);
        if (preg_match('/^.{1,500}$/us', $reason) !== 1) {
            throw new InvalidArgumentException('Enter a leave reason up to 500 characters.');
        }

        $this->database->beginTransaction();
        try {
            $lock = $this->database->prepare('SELECT id FROM users WHERE id = :user_id FOR UPDATE');
            $lock->execute(['user_id' => $userId]);
            if ($lock->fetchColumn() === false) {
                throw new DomainException('The employee account could not be found.');
            }

            $overlap = $this->database->prepare(
                "SELECT id FROM leave_requests
                 WHERE user_id = :user_id
                   AND status IN ('pending', 'approved')
                   AND start_date <= :end_date
                   AND end_date >= :start_date
                 LIMIT 1"
            );
            $overlap->execute([
                'user_id' => $userId,
                'start_date' => $startDate,
                'end_date' => $endDate,
            ]);
            if ($overlap->fetchColumn() !== false) {
                throw new DomainException('You already have a pending or approved leave request for these dates.');
            }

            $insert = $this->database->prepare(
                "INSERT INTO leave_requests (user_id, leave_type, start_date, end_date, reason)
                 VALUES (:user_id, :leave_type, :start_date, :end_date, :reason)"
            );
            $insert->execute([
                'user_id' => $userId,
                'leave_type' => $leaveType,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'reason' => $reason,
            ]);
            $requestId = (int) $this->database->lastInsertId();
            $this->database->commit();
        } catch (Throwable $error) {
            if ($this->database->inTransaction()) {
                $this->database->rollBack();
            }
            throw $error;
        }

        return [
            'id' => $requestId,
            'leaveType' => $leaveType,
            'startDate' => $startDate,
            'endDate' => $endDate,
            'reason' => $reason,
            'status' => 'pending',
        ];
    }

    public function decide(int $requestId, int $reviewerId, string $decision): void
    {
        if (!in_array($decision, ['approved', 'rejected'], true)) {
            throw new InvalidArgumentException('Choose approve or reject for this leave request.');
        }

        $statement = $this->database->prepare(
            "UPDATE leave_requests
             SET status = :status, reviewed_by = :reviewed_by, reviewed_at = CURRENT_TIMESTAMP
             WHERE id = :id AND status = 'pending'"
        );
        $statement->execute([
            'status' => $decision,
            'reviewed_by' => $reviewerId,
            'id' => $requestId,
        ]);
        if ($statement->rowCount() === 1) {
            return;
        }

        $exists = $this->database->prepare('SELECT status FROM leave_requests WHERE id = :id');
        $exists->execute(['id' => $requestId]);
        $status = $exists->fetchColumn();
        if ($status === false) {
            throw new DomainException('Leave request not found.');
        }
        throw new DomainException('This leave request has already been reviewed.');
    }

    private function validDate(mixed $value, string $message): string
    {
        if (!is_string($value)) {
            throw new InvalidArgumentException($message);
        }

        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value, AppTime::timeZone());
        if ($date === false || $date->format('Y-m-d') !== $value) {
            throw new InvalidArgumentException($message);
        }

        return $value;
    }
}
