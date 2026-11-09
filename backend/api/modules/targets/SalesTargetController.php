<?php

declare(strict_types=1);

namespace Arowin\Modules\Targets;

use Arowin\Shared\AppTime;
use Arowin\Shared\ValueFormatter;
use Arowin\Modules\Sales\SalesController;
use PDO;

final class SalesTargetController
{
    public function __construct(private readonly PDO $database)
    {
    }

    public function listMonthlyTargets(string $month): array
    {
        $targetMonth = ValueFormatter::normalizeMonth($month, 'Choose a valid target month.');
        $leaders = $this->database->prepare(
            "SELECT leader.id, leader.employee_id, leader.name, leader.email, leader.mobile,
                    target.target_amount, target.updated_at
             FROM users AS leader
             LEFT JOIN team_leader_sales_targets AS target
               ON target.team_leader_id = leader.id AND target.target_month = :target_month
             WHERE leader.role = 'team_leader' AND leader.is_active = 1
             ORDER BY leader.name ASC, leader.id ASC"
        );
        $leaders->execute(['target_month' => $targetMonth]);

        return [
            'month' => substr($targetMonth, 0, 7),
            'teamLeaders' => array_map(static fn (array $row): array => [
                'id' => (int) $row['id'],
                'employeeId' => (string) $row['employee_id'],
                'fullName' => (string) $row['name'],
                'email' => (string) $row['email'],
                'mobile' => (string) $row['mobile'],
                'targetAmount' => $row['target_amount'] === null ? null : (string) $row['target_amount'],
                'updatedAt' => $row['updated_at'],
            ], $leaders->fetchAll()),
            'history' => $this->listHistory(),
        ];
    }

    public function saveMonthlyTarget(int $teamLeaderId, array $payload, int $configuredBy): array
    {
        if ($teamLeaderId < 1) {
            throw new \InvalidArgumentException('Choose a valid Team Leader.');
        }

        $targetMonth = ValueFormatter::normalizeMonth(
            trim((string) ($payload['month'] ?? '')),
            'Choose a valid target month.'
        );
        $targetAmount = trim((string) ($payload['targetAmount'] ?? ''));
        if (!ValueFormatter::isValidSalesAmount($targetAmount) || (float) $targetAmount <= 0) {
            throw new \InvalidArgumentException('Enter a target greater than zero with up to two decimal places.');
        }

        $leader = $this->database->prepare(
            "SELECT id FROM users WHERE id = :id AND role = 'team_leader' AND is_active = 1 LIMIT 1"
        );
        $leader->execute(['id' => $teamLeaderId]);
        if ($leader->fetchColumn() === false) {
            throw new \DomainException('The selected active Team Leader could not be found.');
        }

        $this->database->beginTransaction();
        try {
            $statement = $this->database->prepare(
                'INSERT INTO team_leader_sales_targets (team_leader_id, target_month, target_amount, configured_by)
                 VALUES (:team_leader_id, :target_month, :target_amount, :configured_by)
                 ON DUPLICATE KEY UPDATE
                     target_amount = VALUES(target_amount),
                     configured_by = VALUES(configured_by)'
            );
            $statement->execute([
                'team_leader_id' => $teamLeaderId,
                'target_month' => $targetMonth,
                'target_amount' => $targetAmount,
                'configured_by' => $configuredBy,
            ]);

            $targetStatement = $this->database->prepare(
                'SELECT id FROM team_leader_sales_targets
                 WHERE team_leader_id = :team_leader_id AND target_month = :target_month
                 LIMIT 1'
            );
            $targetStatement->execute([
                'team_leader_id' => $teamLeaderId,
                'target_month' => $targetMonth,
            ]);
            $targetId = (int) $targetStatement->fetchColumn();
            if ($targetId < 1) {
                throw new \RuntimeException('The saved sales target could not be loaded.');
            }

            $changedAt = AppTime::now()->format('Y-m-d H:i:s.u');
            $historyStatement = $this->database->prepare(
                'INSERT INTO team_leader_sales_target_history
                    (target_id, team_leader_id, target_month, target_amount, configured_by, changed_at)
                 VALUES
                    (:target_id, :team_leader_id, :target_month, :target_amount, :configured_by, :changed_at)'
            );
            $historyStatement->execute([
                'target_id' => $targetId,
                'team_leader_id' => $teamLeaderId,
                'target_month' => $targetMonth,
                'target_amount' => $targetAmount,
                'configured_by' => $configuredBy,
                'changed_at' => $changedAt,
            ]);
            $historyId = (int) $this->database->lastInsertId();

            (new SalesController($this->database))->syncTeamTargetReward(
                $teamLeaderId,
                substr($targetMonth, 0, 7)
            );
            (new SalesController($this->database))->syncTeamGrantIncentive(
                $teamLeaderId,
                substr($targetMonth, 0, 7)
            );
            $this->database->commit();

            return [
                'historyId' => $historyId,
                'teamLeaderId' => $teamLeaderId,
                'month' => substr($targetMonth, 0, 7),
                'targetAmount' => ValueFormatter::formatAmount($targetAmount),
                'changedAt' => $changedAt,
            ];
        } catch (\Throwable $error) {
            if ($this->database->inTransaction()) {
                $this->database->rollBack();
            }

            throw $error;
        }
    }

    public function verifySubEditorPassword(int $userId, string $password): bool
    {
        if ($password === '') {
            return false;
        }

        $statement = $this->database->prepare(
            "SELECT password_hash
             FROM users
             WHERE id = :id AND role = 'sub_editor' AND is_active = 1
             LIMIT 1"
        );
        $statement->execute(['id' => $userId]);
        $passwordHash = $statement->fetchColumn();

        return is_string($passwordHash) && password_verify($password, $passwordHash);
    }

    private function listHistory(): array
    {
        $statement = $this->database->query(
            "SELECT history.id, history.target_month, history.target_amount, history.changed_at,
                    leader.employee_id, leader.name
             FROM team_leader_sales_target_history AS history
             INNER JOIN users AS leader ON leader.id = history.team_leader_id
             WHERE history.target_month >= DATE_FORMAT(DATE_SUB(CURRENT_DATE, INTERVAL 11 MONTH), '%Y-%m-01')
               AND history.target_month <= DATE_FORMAT(CURRENT_DATE, '%Y-%m-01')
             ORDER BY history.target_month DESC, leader.name ASC, history.changed_at DESC, history.id DESC"
        );

        return array_map(static fn (array $row): array => [
            'historyId' => (int) $row['id'],
            'month' => substr((string) $row['target_month'], 0, 7),
            'employeeId' => (string) $row['employee_id'],
            'fullName' => (string) $row['name'],
            'targetAmount' => (string) $row['target_amount'],
            'changedAt' => $row['changed_at'],
        ], $statement->fetchAll());
    }

}
