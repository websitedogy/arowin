<?php

declare(strict_types=1);

use Arowin\Modules\TeamAssignments\TeamStateAssignmentController;
use Arowin\Modules\Territories\IndiaTerritories;
use PHPUnit\Framework\TestCase;

final class TeamStateAssignmentControllerTest extends TestCase
{
    private PDO $database;

    protected function setUp(): void
    {
        $this->database = new PDO('sqlite::memory:');
        $this->database->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->database->exec('CREATE TABLE users (
            id INTEGER PRIMARY KEY,
            employee_id TEXT NOT NULL,
            name TEXT NOT NULL,
            email TEXT NOT NULL,
            mobile TEXT NOT NULL,
            role TEXT NOT NULL,
            is_active INTEGER NOT NULL
        )');
        $this->database->exec('CREATE TABLE team_leader_state_assignments (
            team_leader_id INTEGER NOT NULL,
            state_code TEXT NOT NULL,
            assigned_by INTEGER,
            assigned_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (team_leader_id, state_code)
        )');
        $this->database->exec('CREATE TABLE team_leader_leads (
            id INTEGER PRIMARY KEY,
            team_leader_id INTEGER NOT NULL,
            employee_id TEXT NOT NULL UNIQUE,
            state_code TEXT NOT NULL,
            state_name TEXT NOT NULL,
            district TEXT NOT NULL,
            employee_name TEXT NOT NULL,
            mobile_number TEXT NOT NULL,
            email TEXT NOT NULL,
            address TEXT NOT NULL,
            age INTEGER NOT NULL,
            gender TEXT NOT NULL,
            status TEXT NOT NULL DEFAULT \'new\',
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
        )');
        $this->database->exec("INSERT INTO users (id, employee_id, name, email, mobile, role, is_active) VALUES
            (1, '100001', 'Leader One', 'leader1@example.com', '9000000001', 'team_leader', 1),
            (2, '100002', 'Leader Two', 'leader2@example.com', '9000000002', 'team_leader', 1),
            (3, '200001', 'Telecaller', 'caller@example.com', '9000000003', 'telecaller', 1),
            (4, '300001', 'Sub Editor', 'editor@example.com', '9000000004', 'sub_editor', 1),
            (5, '100003', 'Inactive Leader', 'inactive@example.com', '9000000005', 'team_leader', 0)");
    }

    public function testTheSameStateCanBeAssignedToDifferentTeamLeaders(): void
    {
        $controller = new TeamStateAssignmentController($this->database);

        $controller->assignState(1, 'AP', 4);
        $controller->assignState(2, 'AP', 4);
        $teamLeaders = $controller->listTeamLeaders();

        self::assertCount(2, $teamLeaders);
        self::assertSame('Andhra Pradesh', $teamLeaders[0]['assignedStates'][0]['stateName']);
        self::assertSame('Andhra Pradesh', $teamLeaders[1]['assignedStates'][0]['stateName']);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('This state is already assigned to the selected Team Leader.');

        $controller->assignState(1, 'AP', 4);
    }

    public function testTeamLeaderCanCreateLeadsAndSeeLatestEntryForEachAssignedDistrict(): void
    {
        $controller = new TeamStateAssignmentController($this->database);
        $controller->assignState(1, 'AP', 4);
        $controller->createLeadForDistrict(1, 'AP', $this->leadPayload('First Lead'));
        $latest = $controller->createLeadForDistrict(1, 'AP', $this->leadPayload('Latest Lead'));

        $districtRows = $controller->listAssignedDistricts(1);
        $anantapurRow = array_values(array_filter(
            $districtRows,
            static fn (array $row): bool => $row['district'] === 'Anantapuram'
        ))[0];
        $result = $controller->listLeadsForDistrict(1, 'AP', 'Anantapuram');

        self::assertSame($latest['employeeId'], $anantapurRow['latestLead']['employeeId']);
        self::assertSame('Latest Lead', $anantapurRow['latestLead']['employeeName']);
        self::assertCount(2, $result['leads']);
        self::assertSame(2, $result['counts']['all']);
        self::assertSame(2, $result['counts']['new']);
    }

    public function testLeadStatusUpdatesAreRestrictedToTheirTeamLeaderAndAssignedDistrict(): void
    {
        $controller = new TeamStateAssignmentController($this->database);
        $controller->assignState(1, 'AP', 4);
        $lead = $controller->createLeadForDistrict(1, 'AP', $this->leadPayload('Status Lead'));

        $updated = $controller->updateLeadStatus(1, $lead['id'], 'taken_employee');
        $result = $controller->listLeadsForDistrict(1, 'AP', 'Anantapuram');

        self::assertSame('taken_employee', $updated['status']);
        self::assertSame(1, $result['counts']['taken_employee']);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The lead entry was not found.');

        $controller->updateLeadStatus(2, $lead['id'], 'not_interested');
    }

    public function testTeamLeaderCannotCreateLeadForAnUnassignedState(): void
    {
        $controller = new TeamStateAssignmentController($this->database);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('You are not assigned to view leads for this state.');

        $controller->createLeadForDistrict(1, 'AP', $this->leadPayload('Unassigned Lead'));
    }

    public function testTerritoryDataIncludesAllStatesAndUnionTerritories(): void
    {
        $states = IndiaTerritories::all();

        self::assertCount(36, $states);
        self::assertSame('Anantapuram', IndiaTerritories::findDistrict('AP', 'Anantapuram'));
        self::assertContains('Mahe', IndiaTerritories::findState('PY')['districts']);
        self::assertContains('Kargil', IndiaTerritories::findState('LA')['districts']);
        self::assertSame(8, count(array_intersect(
            array_column($states, 'code'),
            ['AN', 'CH', 'DH', 'DL', 'JK', 'LA', 'LD', 'PY']
        )));
    }

    private function leadPayload(string $name): array
    {
        return [
            'district' => 'Anantapuram',
            'name' => $name,
            'mobile' => '9000000006',
            'email' => 'lead@example.com',
            'address' => 'Main Road',
            'age' => 28,
            'gender' => 'female',
        ];
    }
}
