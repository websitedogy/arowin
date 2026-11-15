<?php

declare(strict_types=1);

namespace Arowin\Modules\Notifications;

use PDO;
use PDOException;
use RuntimeException;
use InvalidArgumentException;

final class NotificationController
{
    private const TRIGGER_LABELS = [
        'new_sales_awaiting_review' => 'New sales awaiting review',
        'sales_approved_or_rejected' => 'Sales approved or rejected',
        'leave_requests' => 'Leave requests',
        'daily_activity_submissions' => 'Daily activity submissions',
        'support_tickets' => 'Support tickets',
    ];

    public function __construct(private readonly PDO $database)
    {
    }

    public function listForSubEditor(int $userId): array
    {
        $settings = $this->triggerSettings();
        $enabled = [];
        foreach ($settings as $setting) {
            $enabled[$setting['eventKey']] = $setting['enabled'];
        }

        $notifications = [];
        foreach (array_keys(self::TRIGGER_LABELS) as $eventKey) {
            if ($enabled[$eventKey] ?? false) {
                array_push($notifications, ...$this->notificationsForEvent($eventKey));
            }
        }

        usort($notifications, static fn (array $first, array $second): int =>
            strcmp($second['occurredAt'], $first['occurredAt'])
        );
        $notifications = array_slice($notifications, 0, 100);

        $reads = $this->database->prepare(
            'SELECT event_key, entity_id FROM notification_reads WHERE user_id = :user_id'
        );
        $reads->execute(['user_id' => $userId]);
        $readKeys = [];
        foreach ($reads->fetchAll(PDO::FETCH_ASSOC) as $read) {
            $readKeys[$read['event_key'] . ':' . $read['entity_id']] = true;
        }

        foreach ($notifications as &$notification) {
            $notification['isRead'] = isset($readKeys[$notification['eventKey'] . ':' . $notification['entityId']]);
        }
        unset($notification);

        return [
            'triggers' => $settings,
            'notifications' => $notifications,
            'unreadCount' => count(array_filter(
                $notifications,
                static fn (array $notification): bool => !$notification['isRead']
            )),
        ];
    }

    public function triggerSettings(): array
    {
        $statement = $this->database->query(
            'SELECT event_key, enabled FROM notification_trigger_settings ORDER BY event_key ASC'
        );
        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
        $settings = [];
        foreach (self::TRIGGER_LABELS as $eventKey => $label) {
            $settings[$eventKey] = [
                'eventKey' => $eventKey,
                'label' => $label,
                'enabled' => null,
            ];
        }
        foreach ($rows as $row) {
            $eventKey = (string) $row['event_key'];
            if (isset($settings[$eventKey])) {
                $settings[$eventKey]['enabled'] = (bool) $row['enabled'];
            }
        }

        foreach ($settings as $setting) {
            if ($setting['enabled'] === null) {
                throw new RuntimeException('Notification trigger settings are incomplete. Run the database schema setup.');
            }
        }

        return array_values($settings);
    }

    public function saveTrigger(array $payload, int $configuredBy): array
    {
        $eventKey = $payload['eventKey'] ?? null;
        $enabled = $payload['enabled'] ?? null;
        if (!is_string($eventKey) || !array_key_exists($eventKey, self::TRIGGER_LABELS) || !is_bool($enabled)) {
            throw new InvalidArgumentException('Choose a valid notification trigger and enabled state.');
        }

        $statement = $this->database->prepare(
            'UPDATE notification_trigger_settings
             SET enabled = :enabled, configured_by = :configured_by
             WHERE event_key = :event_key'
        );
        $statement->execute([
            'enabled' => $enabled ? 1 : 0,
            'configured_by' => $configuredBy,
            'event_key' => $eventKey,
        ]);
        if ($statement->rowCount() === 0) {
            $exists = $this->database->prepare(
                'SELECT event_key FROM notification_trigger_settings WHERE event_key = :event_key'
            );
            $exists->execute(['event_key' => $eventKey]);
            if ($exists->fetchColumn() === false) {
                throw new RuntimeException('Notification trigger settings are missing. Run the database schema setup.');
            }
        }

        foreach ($this->triggerSettings() as $setting) {
            if ($setting['eventKey'] === $eventKey) {
                return $setting;
            }
        }

        throw new RuntimeException('The notification trigger could not be loaded after saving.');
    }

    public function markRead(int $userId, string $eventKey, int $entityId): void
    {
        if (!array_key_exists($eventKey, self::TRIGGER_LABELS) || $entityId < 1) {
            throw new InvalidArgumentException('Choose a valid notification.');
        }

        try {
            $statement = $this->database->prepare(
                'INSERT INTO notification_reads (user_id, event_key, entity_id)
                 VALUES (:user_id, :event_key, :entity_id)'
            );
            $statement->execute([
                'user_id' => $userId,
                'event_key' => $eventKey,
                'entity_id' => $entityId,
            ]);
        } catch (PDOException $error) {
            if ($error->getCode() !== '23000') {
                throw $error;
            }
        }
    }

    private function notificationsForEvent(string $eventKey): array
    {
        $query = match ($eventKey) {
            'new_sales_awaiting_review' => [
                "SELECT sale.id, employee.name AS employee_name, sale.customer_reference AS detail,
                        sale.status, sale.created_at AS occurred_at
                 FROM unlock_sales AS sale
                 INNER JOIN users AS employee ON employee.id = sale.telecaller_id
                 WHERE sale.status = 'pending'
                 ORDER BY sale.created_at DESC, sale.id DESC LIMIT 50",
                'Sale awaiting review',
                '/sales/total',
            ],
            'sales_approved_or_rejected' => [
                "SELECT sale.id, employee.name AS employee_name, sale.customer_reference AS detail,
                        sale.status, sale.reviewed_at AS occurred_at
                 FROM unlock_sales AS sale
                 INNER JOIN users AS employee ON employee.id = sale.telecaller_id
                 WHERE sale.status IN ('approved', 'rejected') AND sale.reviewed_at IS NOT NULL
                 ORDER BY sale.reviewed_at DESC, sale.id DESC LIMIT 50",
                'Sale reviewed',
                '/sales/total',
            ],
            'leave_requests' => [
                "SELECT request.id, employee.name AS employee_name,
                        request.start_date AS detail,
                        request.status, request.created_at AS occurred_at
                 FROM leave_requests AS request
                 INNER JOIN users AS employee ON employee.id = request.user_id
                 ORDER BY request.created_at DESC, request.id DESC LIMIT 50",
                'Leave request',
                '/leave-requests',
            ],
            'daily_activity_submissions' => [
                "SELECT activity.id, employee.name AS employee_name,
                        activity.platform AS detail, activity.status,
                        activity.submitted_at AS occurred_at
                 FROM daily_activities AS activity
                 INNER JOIN users AS employee ON employee.id = activity.user_id
                 ORDER BY activity.submitted_at DESC, activity.id DESC LIMIT 50",
                'Daily activity submitted',
                '/daily-activities',
            ],
            'support_tickets' => [
                "SELECT ticket.id, employee.name AS employee_name,
                        ticket.subject AS detail, ticket.status,
                        ticket.created_at AS occurred_at
                 FROM support_tickets AS ticket
                 INNER JOIN users AS employee ON employee.id = ticket.user_id
                 ORDER BY ticket.created_at DESC, ticket.id DESC LIMIT 50",
                'Support ticket created',
                '/support-tickets',
            ],
            default => throw new InvalidArgumentException('Choose a valid notification trigger.'),
        };

        $rows = $this->database->query($query[0])->fetchAll(PDO::FETCH_ASSOC);

        return array_map(static fn (array $row): array => [
            'eventKey' => $eventKey,
            'entityId' => (int) $row['id'],
            'title' => $query[1],
            'message' => sprintf(
                '%s · %s · %s',
                (string) $row['employee_name'],
                (string) $row['detail'],
                ucfirst((string) $row['status'])
            ),
            'occurredAt' => (string) $row['occurred_at'],
            'route' => $query[2],
        ], $rows);
    }
}
