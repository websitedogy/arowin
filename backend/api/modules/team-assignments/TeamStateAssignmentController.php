<?php

declare(strict_types=1);

namespace Arowin\Modules\TeamAssignments;

use Arowin\Modules\Employees\EmployeeIdGenerator;
use Arowin\Modules\Territories\IndiaTerritories;
use PDO;
use PDOException;

final class TeamStateAssignmentController
{
    public function __construct(private readonly PDO $database)
    {
    }

    public function listTeamLeaders(): array
    {
        $statement = $this->database->query(
            "SELECT leader.id, leader.employee_id, leader.name, leader.email, leader.mobile,
                    assignment.state_code
             FROM users AS leader
             LEFT JOIN team_leader_state_assignments AS assignment
               ON assignment.team_leader_id = leader.id
             WHERE leader.role = 'team_leader' AND leader.is_active = 1
             ORDER BY leader.name ASC, leader.id ASC, assignment.state_code ASC"
        );

        $teamLeaders = [];
        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $leaderId = (int) $row['id'];
            if (!isset($teamLeaders[$leaderId])) {
                $teamLeaders[$leaderId] = [
                    'id' => $leaderId,
                    'employeeId' => (string) $row['employee_id'],
                    'fullName' => (string) $row['name'],
                    'email' => (string) $row['email'],
                    'mobile' => (string) $row['mobile'],
                    'assignedStates' => [],
                ];
            }

            if (is_string($row['state_code'])) {
                $teamLeaders[$leaderId]['assignedStates'][] = $this->stateSummary($row['state_code']);
            }
        }

        return array_values($teamLeaders);
    }

    public function assignState(int $teamLeaderId, string $stateCode, int $assignedBy): void
    {
        $state = IndiaTerritories::findState($stateCode);
        if ($teamLeaderId <= 0 || $assignedBy <= 0 || $state === null) {
            throw new \InvalidArgumentException('Choose an active Team Leader and a valid Indian state.');
        }

        $leader = $this->database->prepare(
            "SELECT id FROM users
             WHERE id = :id AND role = 'team_leader' AND is_active = 1
             LIMIT 1"
        );
        $leader->execute(['id' => $teamLeaderId]);
        if ($leader->fetchColumn() === false) {
            throw new \InvalidArgumentException('The selected Team Leader is not active.');
        }

        $existing = $this->database->prepare(
            'SELECT 1 FROM team_leader_state_assignments
             WHERE team_leader_id = :team_leader_id AND state_code = :state_code
             LIMIT 1'
        );
        $existing->execute([
            'team_leader_id' => $teamLeaderId,
            'state_code' => $state['code'],
        ]);
        if ($existing->fetchColumn() !== false) {
            throw new \DomainException('This state is already assigned to the selected Team Leader.');
        }

        try {
            $assignment = $this->database->prepare(
                'INSERT INTO team_leader_state_assignments (team_leader_id, state_code, assigned_by)
                 VALUES (:team_leader_id, :state_code, :assigned_by)'
            );
            $assignment->execute([
                'team_leader_id' => $teamLeaderId,
                'state_code' => $state['code'],
                'assigned_by' => $assignedBy,
            ]);
        } catch (PDOException $error) {
            if ($error->getCode() === '23000') {
                throw new \DomainException('This state is already assigned to the selected Team Leader.', 0, $error);
            }

            throw $error;
        }
    }

    public function listAssignedStates(int $teamLeaderId): array
    {
        $this->requireActiveTeamLeader($teamLeaderId);

        $statement = $this->database->prepare(
            'SELECT state_code FROM team_leader_state_assignments
             WHERE team_leader_id = :team_leader_id
             ORDER BY state_code ASC'
        );
        $statement->execute(['team_leader_id' => $teamLeaderId]);

        return array_map(
            fn (array $row): array => $this->stateSummary((string) $row['state_code'], true),
            $statement->fetchAll(PDO::FETCH_ASSOC)
        );
    }

    public function listAssignedDistricts(int $teamLeaderId): array
    {
        $states = $this->listAssignedStates($teamLeaderId);
        $latestLeads = $this->database->prepare(
            'SELECT lead.*
             FROM team_leader_leads AS lead
             INNER JOIN (
                 SELECT state_code, district, MAX(id) AS latest_id
                 FROM team_leader_leads
                 WHERE team_leader_id = :team_leader_id
                 GROUP BY state_code, district
             ) AS latest ON latest.latest_id = lead.id
             ORDER BY lead.state_code ASC, lead.district ASC'
        );
        $latestLeads->execute(['team_leader_id' => $teamLeaderId]);

        $leadsByDistrict = [];
        foreach ($latestLeads->fetchAll(PDO::FETCH_ASSOC) as $lead) {
            $key = (string) $lead['state_code'] . '|' . (string) $lead['district'];
            $leadsByDistrict[$key] = $this->serializeLead($lead);
        }

        $districts = [];
        foreach ($states as $state) {
            foreach ($state['districts'] as $district) {
                $key = $state['stateCode'] . '|' . $district;
                $districts[] = [
                    'stateCode' => $state['stateCode'],
                    'stateName' => $state['stateName'],
                    'district' => $district,
                    'latestLead' => $leadsByDistrict[$key] ?? null,
                ];
            }
        }

        return $districts;
    }

    public function listLeadsForDistrict(int $teamLeaderId, string $stateCode, string $districtName): array
    {
        $district = $this->requireAssignedDistrict($teamLeaderId, $stateCode, $districtName);
        $statement = $this->database->prepare(
            'SELECT * FROM team_leader_leads
             WHERE team_leader_id = :team_leader_id
               AND state_code = :state_code
               AND district = :district
             ORDER BY id DESC
             LIMIT 1000'
        );
        $statement->execute([
            'team_leader_id' => $teamLeaderId,
            'state_code' => $district['code'],
            'district' => $district['district'],
        ]);
        $leads = array_map(
            fn (array $row): array => $this->serializeLead($row),
            $statement->fetchAll(PDO::FETCH_ASSOC)
        );

        $counts = $this->database->prepare(
            "SELECT COUNT(*) AS all_count,
                    SUM(CASE WHEN status = 'new' THEN 1 ELSE 0 END) AS new_count,
                    SUM(CASE WHEN status = 'taken_employee' THEN 1 ELSE 0 END) AS taken_employee_count,
                    SUM(CASE WHEN status = 'direct_seller' THEN 1 ELSE 0 END) AS direct_seller_count,
                    SUM(CASE WHEN status = 'not_interested' THEN 1 ELSE 0 END) AS not_interested_count
             FROM team_leader_leads
             WHERE team_leader_id = :team_leader_id
               AND state_code = :state_code
               AND district = :district"
        );
        $counts->execute([
            'team_leader_id' => $teamLeaderId,
            'state_code' => $district['code'],
            'district' => $district['district'],
        ]);
        $countRow = $counts->fetch(PDO::FETCH_ASSOC);

        return [
            'leads' => $leads,
            'counts' => [
                'all' => (int) $countRow['all_count'],
                'new' => (int) $countRow['new_count'],
                'taken_employee' => (int) $countRow['taken_employee_count'],
                'direct_seller' => (int) $countRow['direct_seller_count'],
                'not_interested' => (int) $countRow['not_interested_count'],
            ],
        ];
    }

    public function createLeadForDistrict(int $teamLeaderId, string $stateCode, array $payload): array
    {
        $district = $this->requireAssignedDistrict(
            $teamLeaderId,
            $stateCode,
            (string) ($payload['district'] ?? '')
        );
        $name = trim((string) ($payload['name'] ?? ''));
        if ($name === '' || strlen($name) > 120 || preg_match('/[\x00-\x1F\x7F]/', $name) === 1) {
            throw new \InvalidArgumentException('Enter a name (maximum 120 characters).');
        }

        $mobile = trim((string) ($payload['mobile'] ?? ''));
        if (
            preg_match('/^[0-9+().\-\s]{7,25}$/', $mobile) !== 1
            || preg_match('/[0-9]/', $mobile) !== 1
        ) {
            throw new \InvalidArgumentException('Enter a valid mobile number.');
        }

        $email = trim((string) ($payload['email'] ?? ''));
        if (strlen($email) > 180 || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new \InvalidArgumentException('Enter a valid email address (maximum 180 characters).');
        }

        $address = trim((string) ($payload['address'] ?? ''));
        if (
            $address === ''
            || strlen($address) > 500
            || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $address) === 1
        ) {
            throw new \InvalidArgumentException('Enter an address (maximum 500 characters).');
        }

        $age = filter_var($payload['age'] ?? null, FILTER_VALIDATE_INT);
        if ($age === false || $age < 1 || $age > 120) {
            throw new \InvalidArgumentException('Enter a valid age between 1 and 120.');
        }

        $gender = strtolower(trim((string) ($payload['gender'] ?? '')));
        if (!in_array($gender, ['male', 'female'], true)) {
            throw new \InvalidArgumentException('Choose Male or Female.');
        }

        $employeeId = EmployeeIdGenerator::generate($this->database);
        $insert = $this->database->prepare(
            "INSERT INTO team_leader_leads
                (team_leader_id, employee_id, state_code, state_name, district, employee_name,
                 mobile_number, email, address, age, gender, status)
             VALUES
                (:team_leader_id, :employee_id, :state_code, :state_name, :district, :employee_name,
                 :mobile_number, :email, :address, :age, :gender, 'new')"
        );
        $insert->execute([
            'team_leader_id' => $teamLeaderId,
            'employee_id' => $employeeId,
            'state_code' => $district['code'],
            'state_name' => $district['name'],
            'district' => $district['district'],
            'employee_name' => $name,
            'mobile_number' => $mobile,
            'email' => $email,
            'address' => $address,
            'age' => $age,
            'gender' => $gender,
        ]);

        $leadId = (int) $this->database->lastInsertId();
        $created = $this->database->prepare('SELECT * FROM team_leader_leads WHERE id = :id');
        $created->execute(['id' => $leadId]);
        $row = $created->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            throw new \RuntimeException('The lead was saved but could not be loaded.');
        }

        return $this->serializeLead($row);
    }

    public function updateLeadStatus(int $teamLeaderId, int $leadId, string $status): array
    {
        $allowedStatuses = ['new', 'taken_employee', 'direct_seller', 'not_interested'];
        if (!in_array($status, $allowedStatuses, true)) {
            throw new \InvalidArgumentException('Choose a valid lead status.');
        }

        $this->requireActiveTeamLeader($teamLeaderId);
        $lead = $this->database->prepare(
            'SELECT state_code, district FROM team_leader_leads
             WHERE id = :id AND team_leader_id = :team_leader_id
             LIMIT 1'
        );
        $lead->execute(['id' => $leadId, 'team_leader_id' => $teamLeaderId]);
        $location = $lead->fetch(PDO::FETCH_ASSOC);
        if (!is_array($location)) {
            throw new \InvalidArgumentException('The lead entry was not found.');
        }

        $this->requireAssignedDistrict($teamLeaderId, (string) $location['state_code'], (string) $location['district']);
        $update = $this->database->prepare(
            'UPDATE team_leader_leads SET status = :status
             WHERE id = :id AND team_leader_id = :team_leader_id'
        );
        $update->execute([
            'status' => $status,
            'id' => $leadId,
            'team_leader_id' => $teamLeaderId,
        ]);

        $updated = $this->database->prepare('SELECT * FROM team_leader_leads WHERE id = :id');
        $updated->execute(['id' => $leadId]);
        $row = $updated->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            throw new \RuntimeException('The lead status was updated but the lead could not be loaded.');
        }

        return $this->serializeLead($row);
    }

    private function requireActiveTeamLeader(int $teamLeaderId): void
    {
        $leader = $this->database->prepare(
            "SELECT id FROM users
             WHERE id = :id AND role = 'team_leader' AND is_active = 1
             LIMIT 1"
        );
        $leader->execute(['id' => $teamLeaderId]);
        if ($leader->fetchColumn() === false) {
            throw new \InvalidArgumentException('The selected Team Leader is not active.');
        }
    }

    private function requireAssignedDistrict(int $teamLeaderId, string $stateCode, string $districtName): array
    {
        $state = IndiaTerritories::findState($stateCode);
        if ($state === null) {
            throw new \InvalidArgumentException('Choose a valid Indian state.');
        }

        $this->requireActiveTeamLeader($teamLeaderId);
        $assignment = $this->database->prepare(
            'SELECT 1 FROM team_leader_state_assignments
             WHERE team_leader_id = :team_leader_id AND state_code = :state_code
             LIMIT 1'
        );
        $assignment->execute([
            'team_leader_id' => $teamLeaderId,
            'state_code' => $state['code'],
        ]);
        if ($assignment->fetchColumn() === false) {
            throw new \InvalidArgumentException('You are not assigned to view leads for this state.');
        }

        $district = IndiaTerritories::findDistrict($state['code'], $districtName);
        if ($district === null) {
            throw new \InvalidArgumentException('Choose a valid district for the selected state.');
        }

        return [
            'code' => $state['code'],
            'name' => $state['name'],
            'district' => $district,
        ];
    }

    private function serializeLead(array $row): array
    {
        return [
            'id' => (int) $row['id'],
            'employeeId' => (string) $row['employee_id'],
            'stateCode' => (string) $row['state_code'],
            'stateName' => (string) $row['state_name'],
            'district' => (string) $row['district'],
            'employeeName' => (string) $row['employee_name'],
            'mobile' => (string) $row['mobile_number'],
            'email' => (string) $row['email'],
            'address' => (string) $row['address'],
            'age' => (int) $row['age'],
            'gender' => (string) $row['gender'],
            'status' => (string) $row['status'],
            'createdAt' => (string) $row['created_at'],
        ];
    }

    private function stateSummary(string $stateCode, bool $includeDistricts = false): array
    {
        $state = IndiaTerritories::findState($stateCode);
        if ($state === null) {
            throw new \RuntimeException('An assigned state is missing from the India state and district data.');
        }

        $summary = [
            'stateCode' => $state['code'],
            'stateName' => $state['name'],
        ];
        if ($includeDistricts) {
            $summary['districts'] = $state['districts'];
        }

        return $summary;
    }
}
