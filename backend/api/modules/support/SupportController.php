<?php

declare(strict_types=1);

namespace Arowin\Modules\Support;

use DomainException;
use InvalidArgumentException;
use PDO;
use Throwable;

final class SupportController
{
    private const CATEGORIES = ['account', 'attendance', 'salary', 'sales', 'technical', 'other'];
    private const PRIORITIES = ['low', 'normal', 'high', 'urgent'];
    private const STATUSES = ['open', 'in_progress', 'waiting_on_employee', 'resolved', 'closed'];

    public function __construct(private readonly PDO $database)
    {
    }

    public function listTickets(?int $userId): array
    {
        $sql = 'SELECT ticket.id, ticket.user_id, employee.employee_id, employee.name AS employee_name,
                       employee.role, ticket.subject, ticket.category, ticket.priority, ticket.status,
                       ticket.created_at, ticket.updated_at,
                       (SELECT msg.message FROM support_ticket_messages msg
                        WHERE msg.ticket_id = ticket.id ORDER BY msg.id DESC LIMIT 1) AS latest_message,
                       (SELECT msg.created_at FROM support_ticket_messages msg
                        WHERE msg.ticket_id = ticket.id ORDER BY msg.id DESC LIMIT 1) AS latest_message_at
                FROM support_tickets ticket
                JOIN users employee ON employee.id = ticket.user_id';
        $parameters = [];
        if ($userId !== null) {
            $sql .= ' WHERE ticket.user_id = :user_id';
            $parameters['user_id'] = $userId;
        }
        $sql .= " ORDER BY CASE ticket.status
                    WHEN 'open' THEN 0
                    WHEN 'in_progress' THEN 1
                    WHEN 'waiting_on_employee' THEN 2
                    WHEN 'resolved' THEN 3
                    ELSE 4 END,
                  ticket.updated_at DESC, ticket.id DESC";

        $statement = $this->database->prepare($sql);
        $statement->execute($parameters);

        return array_map(static fn (array $row): array => [
            'id' => (int) $row['id'],
            'employeeId' => (string) $row['employee_id'],
            'employeeName' => (string) $row['employee_name'],
            'role' => (string) $row['role'],
            'subject' => (string) $row['subject'],
            'category' => (string) $row['category'],
            'priority' => (string) $row['priority'],
            'status' => (string) $row['status'],
            'createdAt' => (string) $row['created_at'],
            'updatedAt' => (string) $row['updated_at'],
            'latestMessage' => (string) $row['latest_message'],
            'latestMessageAt' => (string) $row['latest_message_at'],
        ], $statement->fetchAll(PDO::FETCH_ASSOC));
    }

    public function getTicket(int $ticketId, int $requesterId, bool $isSubEditor): array
    {
        $statement = $this->database->prepare(
            'SELECT ticket.id, ticket.user_id, employee.employee_id, employee.name AS employee_name,
                    employee.role, ticket.subject, ticket.category, ticket.priority, ticket.status,
                    ticket.created_at, ticket.updated_at
             FROM support_tickets ticket
             JOIN users employee ON employee.id = ticket.user_id
             WHERE ticket.id = :ticket_id'
        );
        $statement->execute(['ticket_id' => $ticketId]);
        $ticket = $statement->fetch(PDO::FETCH_ASSOC);
        if (!is_array($ticket)) {
            throw new DomainException('Support ticket not found.');
        }
        if (!$isSubEditor && (int) $ticket['user_id'] !== $requesterId) {
            throw new DomainException('You do not have permission to view this support ticket.');
        }

        $messages = $this->database->prepare(
            'SELECT message.id, message.message, message.created_at,
                    sender.id AS sender_id, sender.name AS sender_name, sender.role AS sender_role
             FROM support_ticket_messages message
             JOIN users sender ON sender.id = message.sender_id
             WHERE message.ticket_id = :ticket_id
             ORDER BY message.created_at ASC, message.id ASC'
        );
        $messages->execute(['ticket_id' => $ticketId]);

        return [
            'id' => (int) $ticket['id'],
            'employeeId' => (string) $ticket['employee_id'],
            'employeeName' => (string) $ticket['employee_name'],
            'role' => (string) $ticket['role'],
            'subject' => (string) $ticket['subject'],
            'category' => (string) $ticket['category'],
            'priority' => (string) $ticket['priority'],
            'status' => (string) $ticket['status'],
            'createdAt' => (string) $ticket['created_at'],
            'updatedAt' => (string) $ticket['updated_at'],
            'messages' => array_map(static fn (array $row): array => [
                'id' => (int) $row['id'],
                'message' => (string) $row['message'],
                'createdAt' => (string) $row['created_at'],
                'senderId' => (int) $row['sender_id'],
                'senderName' => (string) $row['sender_name'],
                'senderRole' => (string) $row['sender_role'],
            ], $messages->fetchAll(PDO::FETCH_ASSOC)),
        ];
    }

    public function createTicket(int $userId, array $payload): int
    {
        $subject = $this->text($payload['subject'] ?? null, 5, 140, 'Enter a ticket subject between 5 and 140 characters.');
        $description = $this->text($payload['message'] ?? null, 10, 4000, 'Describe your issue in 10 to 4,000 characters.');
        $category = $payload['category'] ?? null;
        if (!is_string($category) || !in_array($category, self::CATEGORIES, true)) {
            throw new InvalidArgumentException('Choose a valid support category.');
        }
        $priority = $payload['priority'] ?? 'normal';
        if (!is_string($priority) || !in_array($priority, self::PRIORITIES, true)) {
            throw new InvalidArgumentException('Choose a valid priority.');
        }

        $this->database->beginTransaction();
        try {
            $insertTicket = $this->database->prepare(
                'INSERT INTO support_tickets (user_id, subject, category, priority)
                 VALUES (:user_id, :subject, :category, :priority)'
            );
            $insertTicket->execute([
                'user_id' => $userId,
                'subject' => $subject,
                'category' => $category,
                'priority' => $priority,
            ]);
            $ticketId = (int) $this->database->lastInsertId();

            $insertMessage = $this->database->prepare(
                'INSERT INTO support_ticket_messages (ticket_id, sender_id, message)
                 VALUES (:ticket_id, :sender_id, :message)'
            );
            $insertMessage->execute([
                'ticket_id' => $ticketId,
                'sender_id' => $userId,
                'message' => $description,
            ]);
            $this->database->commit();
        } catch (Throwable $error) {
            if ($this->database->inTransaction()) {
                $this->database->rollBack();
            }
            throw $error;
        }

        return $ticketId;
    }

    public function reply(int $ticketId, int $senderId, bool $isSubEditor, array $payload): void
    {
        $message = $this->text($payload['message'] ?? null, 1, 4000, 'Enter a reply up to 4,000 characters.');
        $requestedStatus = $payload['status'] ?? null;
        if ($requestedStatus !== null && (!$isSubEditor || !is_string($requestedStatus)
            || !in_array($requestedStatus, self::STATUSES, true))) {
            throw new InvalidArgumentException('Choose a valid ticket status.');
        }

        $this->database->beginTransaction();
        try {
            $select = $this->database->prepare(
                'SELECT user_id, status FROM support_tickets WHERE id = :ticket_id FOR UPDATE'
            );
            $select->execute(['ticket_id' => $ticketId]);
            $ticket = $select->fetch(PDO::FETCH_ASSOC);
            if (!is_array($ticket)) {
                throw new DomainException('Support ticket not found.');
            }
            if (!$isSubEditor && (int) $ticket['user_id'] !== $senderId) {
                throw new DomainException('You do not have permission to reply to this support ticket.');
            }

            $currentStatus = (string) $ticket['status'];
            if ($isSubEditor) {
                $nextStatus = $requestedStatus ?? match ($currentStatus) {
                    'open', 'in_progress' => 'waiting_on_employee',
                    'resolved', 'closed' => 'in_progress',
                    default => $currentStatus,
                };
            } else {
                $nextStatus = match ($currentStatus) {
                    'waiting_on_employee' => 'in_progress',
                    'resolved', 'closed' => 'open',
                    default => $currentStatus,
                };
            }

            $insertMessage = $this->database->prepare(
                'INSERT INTO support_ticket_messages (ticket_id, sender_id, message)
                 VALUES (:ticket_id, :sender_id, :message)'
            );
            $insertMessage->execute([
                'ticket_id' => $ticketId,
                'sender_id' => $senderId,
                'message' => $message,
            ]);

            $update = $this->database->prepare(
                'UPDATE support_tickets SET status = :status, updated_at = CURRENT_TIMESTAMP WHERE id = :ticket_id'
            );
            $update->execute(['status' => $nextStatus, 'ticket_id' => $ticketId]);
            $this->database->commit();
        } catch (Throwable $error) {
            if ($this->database->inTransaction()) {
                $this->database->rollBack();
            }
            throw $error;
        }
    }

    private function text(mixed $value, int $minimumLength, int $maximumLength, string $errorMessage): string
    {
        if (!is_string($value)) {
            throw new InvalidArgumentException($errorMessage);
        }
        $value = trim($value);
        if (preg_match('/^.{'.$minimumLength.','.$maximumLength.'}$/us', $value) !== 1) {
            throw new InvalidArgumentException($errorMessage);
        }

        return $value;
    }
}
