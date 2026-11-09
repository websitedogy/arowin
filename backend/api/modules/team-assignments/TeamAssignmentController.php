<?php

declare(strict_types=1);

namespace Arowin\Modules\TeamAssignments;

use PDO;
use PDOException;

final class TeamAssignmentController
{
    private ?bool $hasLegacyJoiningDateColumn = null;

    public function __construct(private readonly PDO $database)
    {
    }

    public function listTeamLeaders(): array
    {
        $joiningDate = $this->joiningDateExpression('leader', 'salary');
        $statement = $this->database->query(
            "SELECT leader.id, leader.employee_id, leader.name, leader.email, leader.mobile,
                    leader.created_at, {$joiningDate} AS joining_date, COUNT(assignment.telecaller_id) AS assigned_count,
                    (SELECT COUNT(*)
                     FROM users AS available_employee
                     LEFT JOIN team_assignments AS available_assignment
                       ON available_assignment.telecaller_id = available_employee.id
                     WHERE available_employee.role = 'telecaller'
                       AND available_employee.is_active = 1
                       AND available_assignment.telecaller_id IS NULL) AS available_count
             FROM users AS leader
             LEFT JOIN employee_salaries AS salary ON salary.user_id = leader.id
             LEFT JOIN team_assignments AS assignment ON assignment.team_leader_id = leader.id
             WHERE leader.role = 'team_leader' AND leader.is_active = 1
             GROUP BY leader.id, leader.employee_id, leader.name, leader.email, leader.mobile,
                      leader.created_at, {$joiningDate}
             ORDER BY leader.created_at DESC, leader.id DESC"
        );

        return array_map(static fn (array $row): array => [
            'id' => (int) $row['id'],
            'employeeId' => (string) $row['employee_id'],
            'fullName' => (string) $row['name'],
            'email' => (string) $row['email'],
            'mobile' => (string) $row['mobile'],
            'createdAt' => $row['created_at'],
            'joiningDate' => $row['joining_date'],
            'assignedCount' => (int) $row['assigned_count'],
            'availableCount' => (int) $row['available_count'],
        ], $statement->fetchAll());
    }

    public function listAvailableTelecallers(int $teamLeaderId): array
    {
        $this->requireTeamLeader($teamLeaderId);
        $joiningDate = $this->joiningDateExpression('employee', 'salary');
        $statement = $this->database->query(
            "SELECT employee.id, employee.employee_id, employee.name, employee.email, employee.mobile,
                    employee.created_at, {$joiningDate} AS joining_date
             FROM users AS employee
             LEFT JOIN employee_salaries AS salary ON salary.user_id = employee.id
             LEFT JOIN team_assignments AS assignment ON assignment.telecaller_id = employee.id
             WHERE employee.role = 'telecaller'
               AND employee.is_active = 1
               AND assignment.telecaller_id IS NULL
             ORDER BY employee.created_at DESC, employee.id DESC"
        );

        return array_map(static fn (array $row): array => [
            'id' => (int) $row['id'],
            'employeeId' => (string) $row['employee_id'],
            'fullName' => (string) $row['name'],
            'email' => (string) $row['email'],
            'mobile' => (string) $row['mobile'],
            'createdAt' => $row['created_at'],
            'joiningDate' => $row['joining_date'],
        ], $statement->fetchAll());
    }

    public function listAssignedTelecallers(int $teamLeaderId): array
    {
        $leader = $this->database->prepare(
            "SELECT id FROM users WHERE id = :id AND role = 'team_leader' LIMIT 1"
        );
        $leader->execute(['id' => $teamLeaderId]);
        if ($leader->fetchColumn() === false) {
            throw new \InvalidArgumentException('The selected Team Leader does not exist.');
        }

        $joiningDate = $this->joiningDateExpression('employee', 'salary');
        $statement = $this->database->prepare(
            "SELECT employee.id, employee.employee_id, employee.name, employee.email, employee.mobile,
                    employee.is_active, employee.created_at, {$joiningDate} AS joining_date, assignment.assigned_at
             FROM team_assignments AS assignment
             INNER JOIN users AS employee ON employee.id = assignment.telecaller_id
             LEFT JOIN employee_salaries AS salary ON salary.user_id = employee.id
             WHERE assignment.team_leader_id = :team_leader_id
             ORDER BY assignment.assigned_at DESC, employee.name ASC"
        );
        $statement->execute(['team_leader_id' => $teamLeaderId]);

        return array_map(static fn (array $row): array => [
            'id' => (int) $row['id'],
            'employeeId' => (string) $row['employee_id'],
            'fullName' => (string) $row['name'],
            'email' => (string) $row['email'],
            'mobile' => (string) $row['mobile'],
            'status' => (int) $row['is_active'] === 1 ? 'Active' : 'Inactive',
            'createdAt' => $row['created_at'],
            'joiningDate' => $row['joining_date'],
            'assignedAt' => $row['assigned_at'],
        ], $statement->fetchAll());
    }

    public function listAssignmentHistory(): array
    {
        $leaderJoiningDate = $this->joiningDateExpression('leader', 'leader_salary');
        $statement = $this->database->query(
            "SELECT log.id, log.created_at,
                    JSON_UNQUOTE(JSON_EXTRACT(log.metadata, '$.team_leader_id')) AS team_leader_id,
                    COALESCE(JSON_UNQUOTE(JSON_EXTRACT(log.metadata, '$.team_leader_employee_id')), leader.employee_id) AS team_leader_employee_id,
                    COALESCE(JSON_UNQUOTE(JSON_EXTRACT(log.metadata, '$.team_leader_name')), leader.name) AS team_leader_name,
                    COALESCE(JSON_UNQUOTE(JSON_EXTRACT(log.metadata, '$.team_leader_mobile')), leader.mobile) AS team_leader_mobile,
                    COALESCE(JSON_UNQUOTE(JSON_EXTRACT(log.metadata, '$.team_leader_email')), leader.email) AS team_leader_email,
                    COALESCE(JSON_UNQUOTE(JSON_EXTRACT(log.metadata, '$.team_leader_created_at')), leader.created_at) AS team_leader_created_at,
                    COALESCE(JSON_UNQUOTE(JSON_EXTRACT(log.metadata, '$.team_leader_joining_date')), {$leaderJoiningDate}) AS team_leader_joining_date,
                    JSON_UNQUOTE(JSON_EXTRACT(log.metadata, '$.telecaller_id')) AS telecaller_id,
                    COALESCE(JSON_UNQUOTE(JSON_EXTRACT(log.metadata, '$.telecaller_employee_id')), telecaller.employee_id) AS telecaller_employee_id,
                    COALESCE(JSON_UNQUOTE(JSON_EXTRACT(log.metadata, '$.telecaller_name')), telecaller.name) AS telecaller_name
             FROM activity_logs AS log
             LEFT JOIN users AS leader
               ON leader.id = CAST(JSON_UNQUOTE(JSON_EXTRACT(log.metadata, '$.team_leader_id')) AS UNSIGNED)
             LEFT JOIN employee_salaries AS leader_salary ON leader_salary.user_id = leader.id
             LEFT JOIN users AS telecaller
               ON telecaller.id = CAST(JSON_UNQUOTE(JSON_EXTRACT(log.metadata, '$.telecaller_id')) AS UNSIGNED)
             WHERE log.action = 'team.telecaller.assigned'
             ORDER BY log.created_at DESC, log.id DESC"
        );

        return array_map(static fn (array $row): array => [
            'id' => (int) $row['id'],
            'teamLeaderId' => (int) $row['team_leader_id'],
            'teamLeaderEmployeeId' => (string) ($row['team_leader_employee_id'] ?? ''),
            'teamLeaderName' => (string) ($row['team_leader_name'] ?? 'Deleted Team Leader'),
            'teamLeaderMobile' => (string) ($row['team_leader_mobile'] ?? ''),
            'teamLeaderEmail' => (string) ($row['team_leader_email'] ?? ''),
            'teamLeaderCreatedAt' => $row['team_leader_created_at'],
            'teamLeaderJoiningDate' => $row['team_leader_joining_date'],
            'telecallerId' => (int) $row['telecaller_id'],
            'telecallerEmployeeId' => (string) ($row['telecaller_employee_id'] ?? ''),
            'telecallerName' => (string) ($row['telecaller_name'] ?? 'Deleted Telecaller'),
            'assignedAt' => $row['created_at'],
        ], $statement->fetchAll());
    }

    public function assignTelecaller(int $teamLeaderId, int $telecallerId, int $assignedBy): void
    {
        if ($teamLeaderId <= 0 || $telecallerId <= 0 || $teamLeaderId === $telecallerId) {
            throw new \InvalidArgumentException('Choose a valid Team Leader and Telecaller.');
        }

        $this->database->beginTransaction();

        try {
            $teamLeader = $this->findActiveUser($teamLeaderId, 'team_leader');
            $telecaller = $this->findActiveUser($telecallerId, 'telecaller');

            if ($teamLeader === null) {
                throw new \InvalidArgumentException('The selected Team Leader is not active.');
            }

            if ($telecaller === null) {
                throw new \InvalidArgumentException('The selected Telecaller is not active.');
            }

            $assignment = $this->database->prepare(
                'INSERT INTO team_assignments (team_leader_id, telecaller_id, assigned_by)
                 VALUES (:team_leader_id, :telecaller_id, :assigned_by)'
            );
            $assignment->execute([
                'team_leader_id' => $teamLeaderId,
                'telecaller_id' => $telecallerId,
                'assigned_by' => $assignedBy,
            ]);

            $activity = $this->database->prepare(
                "INSERT INTO activity_logs (user_id, action, metadata)
                 VALUES (:user_id, 'team.telecaller.assigned', :metadata)"
            );
            $activity->execute([
                'user_id' => $assignedBy,
                'metadata' => json_encode([
                    'team_leader_id' => $teamLeaderId,
                    'team_leader_name' => $teamLeader['name'],
                    'team_leader_employee_id' => $teamLeader['employee_id'],
                    'team_leader_mobile' => $teamLeader['mobile'],
                    'team_leader_email' => $teamLeader['email'],
                    'team_leader_created_at' => $teamLeader['created_at'],
                    'team_leader_joining_date' => $teamLeader['joining_date'],
                    'telecaller_id' => $telecallerId,
                    'telecaller_name' => $telecaller['name'],
                    'telecaller_employee_id' => $telecaller['employee_id'],
                ], JSON_THROW_ON_ERROR),
            ]);

            $this->database->commit();
        } catch (PDOException $error) {
            if ($this->database->inTransaction()) {
                $this->database->rollBack();
            }

            if ($error->getCode() === '23000') {
                throw new \DomainException('This Telecaller has already been assigned to a Team Leader.', 0, $error);
            }

            throw $error;
        } catch (\Throwable $error) {
            if ($this->database->inTransaction()) {
                $this->database->rollBack();
            }

            throw $error;
        }
    }

    public function teamDashboard(int $teamLeaderId): array
    {
        $activities = $this->database->prepare(
            "SELECT metadata, created_at
             FROM activity_logs
             WHERE action = 'team.telecaller.assigned'
               AND JSON_UNQUOTE(JSON_EXTRACT(metadata, '$.team_leader_id')) = :team_leader_id
             ORDER BY created_at DESC, id DESC
             LIMIT 5"
        );
        $activities->execute(['team_leader_id' => (string) $teamLeaderId]);

        $history = $this->database->prepare(
            "SELECT
                COALESCE(
                    JSON_UNQUOTE(JSON_EXTRACT(metadata, '$.telecaller_employee_id')),
                    employee.employee_id
                ) AS employee_id,
                COALESCE(
                    JSON_UNQUOTE(JSON_EXTRACT(metadata, '$.telecaller_name')),
                    employee.name,
                    'Deleted Telecaller'
                ) AS employee_name,
                log.created_at
             FROM activity_logs AS log
             LEFT JOIN users AS employee
               ON employee.id = CAST(JSON_UNQUOTE(JSON_EXTRACT(log.metadata, '$.telecaller_id')) AS UNSIGNED)
             WHERE action = 'team.telecaller.assigned'
               AND JSON_UNQUOTE(JSON_EXTRACT(metadata, '$.team_leader_id')) = :team_leader_id
               AND log.created_at >= DATE_FORMAT(DATE_SUB(CURRENT_DATE, INTERVAL 11 MONTH), '%Y-%m-01')
             ORDER BY log.created_at DESC, log.id DESC"
        );
        $history->execute(['team_leader_id' => (string) $teamLeaderId]);

        $salesTarget = $this->database->prepare(
            'SELECT target_amount, target_month
             FROM team_leader_sales_targets
             WHERE team_leader_id = :team_leader_id
               AND target_month = DATE_FORMAT(CURRENT_DATE, \'%Y-%m-01\')
             LIMIT 1'
        );
        $salesTarget->execute(['team_leader_id' => $teamLeaderId]);
        $currentSalesTarget = $salesTarget->fetch();

        return [
            'members' => $this->listAssignedTelecallers($teamLeaderId),
            'salesTarget' => is_array($currentSalesTarget) ? [
                'amount' => (string) $currentSalesTarget['target_amount'],
                'month' => substr((string) $currentSalesTarget['target_month'], 0, 7),
            ] : null,
            'activities' => array_map(static function (array $row): array {
                $metadata = json_decode((string) $row['metadata'], true);

                return [
                    'title' => sprintf(
                        '%s assigned to your team',
                        is_array($metadata) ? (string) ($metadata['telecaller_name'] ?? 'A Telecaller') : 'A Telecaller'
                    ),
                    'time' => $row['created_at'],
                ];
            }, $activities->fetchAll()),
            'history' => array_map(static fn (array $row): array => [
                'employeeId' => (string) ($row['employee_id'] ?? ''),
                'employeeName' => (string) $row['employee_name'],
                'activity' => 'Assigned to your team',
                'salesTarget' => null,
                'time' => $row['created_at'],
            ], $history->fetchAll()),
        ];
    }

    private function requireTeamLeader(int $teamLeaderId): void
    {
        if ($this->findActiveUser($teamLeaderId, 'team_leader') === null) {
            throw new \InvalidArgumentException('The selected Team Leader is not active.');
        }
    }

    private function findActiveUser(int $userId, string $role): ?array
    {
        $joiningDate = $this->joiningDateExpression('employee', 'salary');
        $statement = $this->database->prepare(
            "SELECT employee.id, employee.name, employee.employee_id, employee.mobile, employee.email,
                    employee.created_at, {$joiningDate} AS joining_date
             FROM users AS employee
             LEFT JOIN employee_salaries AS salary ON salary.user_id = employee.id
             WHERE employee.id = :id AND employee.role = :role AND employee.is_active = 1
             LIMIT 1
             FOR UPDATE"
        );
        $statement->execute(['id' => $userId, 'role' => $role]);
        $user = $statement->fetch();

        return is_array($user) ? $user : null;
    }

    private function joiningDateExpression(string $userAlias, string $salaryAlias): string
    {
        if ($this->hasLegacyJoiningDateColumn === null) {
            $column = $this->database->query(
                "SELECT COUNT(*)
                 FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME = 'users'
                   AND COLUMN_NAME = 'joining_date'"
            );
            $this->hasLegacyJoiningDateColumn = (int) $column->fetchColumn() > 0;
        }

        if ($this->hasLegacyJoiningDateColumn) {
            return "COALESCE({$salaryAlias}.joining_date, {$userAlias}.joining_date)";
        }

        return "{$salaryAlias}.joining_date";
    }
}
