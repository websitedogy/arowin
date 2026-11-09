<?php

declare(strict_types=1);

use Arowin\Modules\DailyActivities\DailyActivityController;
use Arowin\Shared\AppTime;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../../api/bootstrap.php';

final class DailyActivityBonusTest extends TestCase
{
    private PDO $database;
    private int $reviewerId = 2;

    protected function setUp(): void
    {
        $this->database = new DailyActivityBonusTestPdo();
        $this->database->exec('CREATE TABLE users (id INTEGER PRIMARY KEY)');
        $this->database->exec('CREATE TABLE daily_activities (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            platform TEXT NOT NULL,
            bonus_amount REAL,
            submitted_at TEXT NOT NULL,
            status TEXT NOT NULL,
            verified_by INTEGER,
            verified_at TEXT,
            reviewed_at TEXT,
            rejection_reason TEXT
        )');
        $this->database->exec('CREATE TABLE daily_activity_screenshots (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            activity_id INTEGER NOT NULL
        )');
        $this->database->exec('CREATE TABLE daily_activity_bonus_rates (
            platform TEXT PRIMARY KEY,
            amount REAL NOT NULL
        )');
        $this->database->exec('CREATE TABLE daily_activity_bonus_limits (
            id INTEGER PRIMARY KEY,
            daily_cap_amount REAL NOT NULL
        )');
        $this->database->exec('INSERT INTO users (id) VALUES (1), (2)');
        $this->database->exec("INSERT INTO daily_activity_bonus_rates (platform, amount) VALUES ('Instagram', 4), ('Facebook', 3)");
        $this->database->exec('INSERT INTO daily_activity_bonus_limits (id, daily_cap_amount) VALUES (1, 5)');
    }

    public function testSettingsReturnPlatformRatesAndDailyMaximum(): void
    {
        $settings = (new DailyActivityController($this->database))->bonusSettings();

        $this->assertSame(4.0, $settings['rates']['Instagram']);
        $this->assertSame(3.0, $settings['rates']['Facebook']);
        $this->assertSame(0.0, $settings['rates']['YouTube']);
        $this->assertSame(5.0, $settings['dailyCapAmount']);
    }

    public function testApprovedActivitiesUsePlatformRateAndRespectDailyMaximum(): void
    {
        $date = AppTime::now()->format('Y-m-d');
        $insert = $this->database->prepare(
            'INSERT INTO daily_activities (user_id, platform, submitted_at, status)
             VALUES (1, :platform, :submitted_at, \'pending\')'
        );
        $insert->execute(['platform' => 'Instagram', 'submitted_at' => $date . ' 08:00:00']);
        $firstId = (int) $this->database->lastInsertId();
        $insert->execute(['platform' => 'Facebook', 'submitted_at' => $date . ' 08:30:00']);
        $secondId = (int) $this->database->lastInsertId();
        $insert->execute(['platform' => 'Instagram', 'submitted_at' => $date . ' 09:00:00']);
        $thirdId = (int) $this->database->lastInsertId();

        $controller = new DailyActivityController($this->database);
        $controller->decide($firstId, $this->reviewerId, 'approve', null);
        $this->database->exec("UPDATE daily_activity_bonus_rates SET amount = 1 WHERE platform = 'Facebook'");
        $controller->decide($secondId, $this->reviewerId, 'approve', null);
        $controller->decide($thirdId, $this->reviewerId, 'approve', null);

        $amounts = $this->database->query(
            'SELECT bonus_amount FROM daily_activities ORDER BY id ASC'
        )->fetchAll(PDO::FETCH_COLUMN);
        $this->assertSame([4.0, 1.0, 0.0], array_map('floatval', $amounts));
    }

    public function testSettingsRejectInvalidPlatformAmounts(): void
    {
        $settings = (new DailyActivityController($this->database))->bonusSettings();
        $settings['rates']['Instagram'] = 'not-an-amount';
        $controller = new DailyActivityController($this->database);

        $this->expectException(InvalidArgumentException::class);
        $controller->saveBonusSettings([
            'rates' => $settings['rates'],
            'dailyCapAmount' => $settings['dailyCapAmount'],
        ], $this->reviewerId);
    }
}

final class DailyActivityBonusTestPdo extends PDO
{
    public function __construct()
    {
        parent::__construct('sqlite::memory:');
        $this->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }

    public function prepare(string $query, array $driverOptions = []): PDOStatement
    {
        $query = preg_replace('/\s+FOR UPDATE\b/i', '', $query);

        return parent::prepare($query, $driverOptions);
    }

    public function query(string $query, ?int $fetchMode = null, mixed ...$params): PDOStatement|false
    {
        $query = preg_replace('/\s+FOR UPDATE\b/i', '', $query);

        return parent::query($query, $fetchMode, ...$params);
    }
}
