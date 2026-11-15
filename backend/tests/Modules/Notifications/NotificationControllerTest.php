<?php

declare(strict_types=1);

use Arowin\Modules\Notifications\NotificationController;
use PHPUnit\Framework\TestCase;

final class NotificationControllerTest extends TestCase
{
    public function testConfiguredNotificationsAndReadStateReflectCurrentEvents(): void
    {
        $database = new PDO('sqlite::memory:');
        $database->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $database->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT NOT NULL)');
        $database->exec('CREATE TABLE unlock_sales (
            id INTEGER PRIMARY KEY,
            telecaller_id INTEGER,
            customer_reference TEXT,
            status TEXT,
            created_at TEXT,
            reviewed_at TEXT
        )');
        $database->exec('CREATE TABLE leave_requests (
            id INTEGER PRIMARY KEY,
            user_id INTEGER,
            start_date TEXT,
            end_date TEXT,
            status TEXT,
            created_at TEXT
        )');
        $database->exec('CREATE TABLE daily_activities (
            id INTEGER PRIMARY KEY,
            user_id INTEGER,
            platform TEXT,
            status TEXT,
            submitted_at TEXT
        )');
        $database->exec('CREATE TABLE support_tickets (
            id INTEGER PRIMARY KEY,
            user_id INTEGER,
            subject TEXT,
            status TEXT,
            created_at TEXT
        )');
        $database->exec('CREATE TABLE notification_trigger_settings (
            event_key TEXT PRIMARY KEY,
            enabled INTEGER NOT NULL,
            configured_by INTEGER
        )');
        $database->exec('CREATE TABLE notification_reads (
            user_id INTEGER,
            event_key TEXT,
            entity_id INTEGER,
            read_at TEXT DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (user_id, event_key, entity_id)
        )');
        $database->exec("INSERT INTO users (id, name) VALUES (10, 'Sub Editor'), (20, 'Employee')");
        $database->exec("INSERT INTO unlock_sales
            (id, telecaller_id, customer_reference, status, created_at, reviewed_at) VALUES
            (1, 20, 'Pending Sale', 'pending', '2025-01-03 10:00:00', NULL),
            (2, 20, 'Approved Sale', 'approved', '2025-01-02 10:00:00', '2025-01-04 10:00:00')");
        $database->exec("INSERT INTO leave_requests
            (id, user_id, start_date, end_date, status, created_at)
            VALUES (3, 20, '2025-01-10', '2025-01-11', 'pending', '2025-01-03 11:00:00')");
        $database->exec("INSERT INTO daily_activities
            (id, user_id, platform, status, submitted_at)
            VALUES (4, 20, 'Instagram', 'pending', '2025-01-03 12:00:00')");
        $database->exec("INSERT INTO support_tickets
            (id, user_id, subject, status, created_at)
            VALUES (5, 20, 'Login issue', 'open', '2025-01-03 13:00:00')");
        $database->exec("INSERT INTO notification_trigger_settings (event_key, enabled) VALUES
            ('new_sales_awaiting_review', 1),
            ('sales_approved_or_rejected', 1),
            ('leave_requests', 1),
            ('daily_activity_submissions', 1),
            ('support_tickets', 1)");

        $controller = new NotificationController($database);
        $initial = $controller->listForSubEditor(10);

        self::assertCount(5, $initial['notifications']);
        self::assertSame(5, $initial['unreadCount']);

        $controller->markRead(10, 'new_sales_awaiting_review', 1);
        $controller->saveTrigger(['eventKey' => 'support_tickets', 'enabled' => false], 10);
        $updated = $controller->listForSubEditor(10);

        self::assertCount(4, $updated['notifications']);
        self::assertSame(3, $updated['unreadCount']);
        self::assertFalse(
            array_column($updated['triggers'], 'enabled', 'eventKey')['support_tickets']
        );
        self::assertTrue(
            array_values(array_filter(
                $updated['notifications'],
                static fn (array $notification): bool => $notification['eventKey'] === 'new_sales_awaiting_review'
            ))[0]['isRead']
        );
    }
}
