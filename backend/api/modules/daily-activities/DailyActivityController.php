<?php

declare(strict_types=1);

namespace Arowin\Modules\DailyActivities;

use Arowin\Shared\AppTime;
use DomainException;
use InvalidArgumentException;
use PDO;
use RuntimeException;
use Throwable;

final class DailyActivityController
{
    private const PLATFORMS = [
        'Instagram',
        'Facebook',
        'WhatsApp Status',
        'LinkedIn',
        'YouTube',
        'X (Twitter)',
        'Telegram',
        'Snapchat',
        'Pinterest',
        'Threads',
        'Reddit',
        'Other',
    ];
    private const MAX_SCREENSHOTS = 5;
    private const MAX_SCREENSHOT_BYTES = 4_000_000;
    private const MAX_YOUTUBE_VIDEOS = 2;
    private const MAX_YOUTUBE_VIDEO_BYTES = 50_000_000;

    public function __construct(private readonly PDO $database)
    {
    }

    public function bonusSettings(): array
    {
        $rates = array_fill_keys(self::PLATFORMS, 0.0);
        $statement = $this->database->query(
            'SELECT platform, amount FROM daily_activity_bonus_rates ORDER BY platform ASC'
        );
        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $platform = (string) $row['platform'];
            if (array_key_exists($platform, $rates)) {
                $rates[$platform] = (float) $row['amount'];
            }
        }

        $dailyCap = $this->database->query(
            'SELECT daily_cap_amount FROM daily_activity_bonus_limits WHERE id = 1'
        )->fetchColumn();
        if ($dailyCap === false) {
            throw new RuntimeException('Daily activity bonus settings are missing. Run the database schema setup.');
        }

        return [
            'platforms' => self::PLATFORMS,
            'rates' => $rates,
            'dailyCapAmount' => (float) $dailyCap,
        ];
    }

    public function saveBonusSettings(array $payload, int $configuredBy): array
    {
        $rates = $payload['rates'] ?? null;
        if (!is_array($rates) || count($rates) !== count(self::PLATFORMS)) {
            throw new InvalidArgumentException('Enter an amount for every daily activity platform.');
        }

        $validatedRates = [];
        foreach (self::PLATFORMS as $platform) {
            $rawAmount = $rates[$platform] ?? null;
            if ((!is_string($rawAmount) && !is_int($rawAmount) && !is_float($rawAmount))
                || !is_numeric($rawAmount) || !is_finite((float) $rawAmount)) {
                throw new InvalidArgumentException('Enter a valid non-negative amount for every platform.');
            }
            $amount = round((float) $rawAmount, 2);
            if ($amount < 0 || $amount > 99999999.99) {
                throw new InvalidArgumentException('Platform amounts must be between 0 and 99999999.99.');
            }
            $validatedRates[$platform] = $amount;
        }

        $rawCap = $payload['dailyCapAmount'] ?? null;
        if ((!is_string($rawCap) && !is_int($rawCap) && !is_float($rawCap))
            || !is_numeric($rawCap) || !is_finite((float) $rawCap)) {
            throw new InvalidArgumentException('Enter a valid daily activity bonus maximum.');
        }
        $dailyCap = round((float) $rawCap, 2);
        if ($dailyCap < 0 || $dailyCap > 99999999.99) {
            throw new InvalidArgumentException('The daily activity bonus maximum must be between 0 and 99999999.99.');
        }

        $this->database->beginTransaction();
        try {
            $statement = $this->database->prepare(
                'INSERT INTO daily_activity_bonus_rates (platform, amount, configured_by)
                 VALUES (:platform, :amount, :configured_by)
                 ON DUPLICATE KEY UPDATE
                    amount = VALUES(amount),
                    configured_by = VALUES(configured_by),
                    updated_at = CURRENT_TIMESTAMP'
            );
            foreach ($validatedRates as $platform => $amount) {
                $statement->execute([
                    'platform' => $platform,
                    'amount' => $amount,
                    'configured_by' => $configuredBy,
                ]);
            }

            $limit = $this->database->prepare(
                'INSERT INTO daily_activity_bonus_limits (id, daily_cap_amount, configured_by)
                 VALUES (1, :amount, :configured_by)
                 ON DUPLICATE KEY UPDATE
                    daily_cap_amount = VALUES(daily_cap_amount),
                    configured_by = VALUES(configured_by),
                    updated_at = CURRENT_TIMESTAMP'
            );
            $limit->execute(['amount' => $dailyCap, 'configured_by' => $configuredBy]);
            $this->database->commit();
        } catch (\Throwable $error) {
            if ($this->database->inTransaction()) {
                $this->database->rollBack();
            }
            throw $error;
        }

        return $this->bonusSettings();
    }

    public function listActivities(?int $userId): array
    {
        $sql = 'SELECT a.id, a.user_id, u.employee_id, u.name AS employee_name, u.role,
                       a.platform, a.submitted_at, a.status, a.verified_at, a.reviewed_at,
                       a.rejection_reason, reviewer.name AS reviewer_name
                FROM daily_activities a
                JOIN users u ON u.id = a.user_id
                LEFT JOIN users reviewer ON reviewer.id = a.verified_by';
        $parameters = [];
        if ($userId !== null) {
            $sql .= ' WHERE a.user_id = :user_id';
            $parameters['user_id'] = $userId;
        }
        $sql .= ' ORDER BY a.submitted_at DESC, a.id DESC';

        $statement = $this->database->prepare($sql);
        $statement->execute($parameters);
        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
        if ($rows === []) {
            return [];
        }

        $activities = [];
        $activityIds = [];
        foreach ($rows as $row) {
            $id = (int) $row['id'];
            $activityIds[] = $id;
            $activities[$id] = [
                'id' => $id,
                'employeeId' => (string) $row['employee_id'],
                'employeeName' => (string) $row['employee_name'],
                'role' => (string) $row['role'],
                'platform' => (string) $row['platform'],
                'submittedAt' => (string) $row['submitted_at'],
                'status' => (string) $row['status'],
                'verifiedAt' => $row['verified_at'],
                'reviewedAt' => $row['reviewed_at'],
                'rejectionReason' => $row['rejection_reason'],
                'reviewerName' => $row['reviewer_name'],
                'screenshots' => [],
                'videos' => [],
            ];
        }

        $placeholders = implode(',', array_fill(0, count($activityIds), '?'));
        $screenshotsQuery = $this->database->prepare(
            "SELECT id, activity_id, original_name, mime_type, file_size
             FROM daily_activity_screenshots
             WHERE activity_id IN ({$placeholders})
             ORDER BY id ASC"
        );
        $screenshotsQuery->execute($activityIds);
        foreach ($screenshotsQuery->fetchAll(PDO::FETCH_ASSOC) as $screenshot) {
            $activityId = (int) $screenshot['activity_id'];
            $activities[$activityId]['screenshots'][] = [
                'id' => (int) $screenshot['id'],
                'name' => (string) $screenshot['original_name'],
                'mimeType' => (string) $screenshot['mime_type'],
                'size' => (int) $screenshot['file_size'],
            ];
        }

        $videosQuery = $this->database->prepare(
            "SELECT id, activity_id, original_name, mime_type, file_size
             FROM daily_activity_videos
             WHERE activity_id IN ({$placeholders})
             ORDER BY id ASC"
        );
        $videosQuery->execute($activityIds);
        foreach ($videosQuery->fetchAll(PDO::FETCH_ASSOC) as $video) {
            $activityId = (int) $video['activity_id'];
            $activities[$activityId]['videos'][] = [
                'id' => (int) $video['id'],
                'name' => (string) $video['original_name'],
                'mimeType' => (string) $video['mime_type'],
                'size' => (int) $video['file_size'],
            ];
        }

        return array_values($activities);
    }

    public function submit(int $userId, array $payload, mixed $screenshotUpload, mixed $videoUpload): array
    {
        $platform = $payload['platform'] ?? null;
        if (!is_string($platform) || !in_array($platform, self::PLATFORMS, true)) {
            throw new InvalidArgumentException('Choose a platform for this activity.');
        }

        $isYouTube = $platform === 'YouTube';
        $files = $this->normalizeUploads(
            $isYouTube ? $videoUpload : $screenshotUpload,
            $isYouTube ? self::MAX_YOUTUBE_VIDEOS : self::MAX_SCREENSHOTS,
            $isYouTube ? 'video' : 'screenshot'
        );
        $validatedFiles = [];
        foreach ($files as $file) {
            if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                throw new InvalidArgumentException($this->uploadErrorMessage((int) $file['error'], $isYouTube));
            }
            if (!is_string($file['tmp_name'] ?? null) || !is_uploaded_file($file['tmp_name'])) {
                throw new InvalidArgumentException($isYouTube
                    ? 'Choose a valid YouTube Short video file to upload.'
                    : 'Choose a valid screenshot file to upload.');
            }
            $size = (int) ($file['size'] ?? 0);
            $maxFileSize = $isYouTube ? self::MAX_YOUTUBE_VIDEO_BYTES : self::MAX_SCREENSHOT_BYTES;
            if ($size < 1 || $size > $maxFileSize) {
                throw new InvalidArgumentException($isYouTube
                    ? 'Each YouTube Short video must be smaller than 50 MB.'
                    : 'Each screenshot must be smaller than 4 MB.');
            }

            $fileInfo = new \finfo(FILEINFO_MIME_TYPE);
            $mimeType = $fileInfo->file($file['tmp_name']);
            if ($isYouTube) {
                if (!is_string($mimeType) || !in_array($mimeType, ['video/mp4', 'video/webm'], true)) {
                    throw new InvalidArgumentException('YouTube Shorts must be MP4 or WebM videos.');
                }
            } else {
                if (!is_string($mimeType) || !in_array($mimeType, ['image/jpeg', 'image/png', 'image/webp'], true)) {
                    throw new InvalidArgumentException('Screenshots must be JPEG, PNG, or WebP images.');
                }
                $imageInfo = getimagesize($file['tmp_name']);
                if (!is_array($imageInfo)
                    || ($imageInfo['mime'] ?? '') !== $mimeType
                    || (int) ($imageInfo[0] ?? 0) < 1
                    || (int) ($imageInfo[1] ?? 0) < 1
                    || (int) $imageInfo[0] * (int) $imageInfo[1] > 40_000_000) {
                    throw new InvalidArgumentException('One of the uploaded files is not a valid image.');
                }
            }

            $validatedFiles[] = [
                ...$file,
                'mime_type' => $mimeType,
                'file_size' => $size,
                'original_name' => $this->safeOriginalName((string) ($file['name'] ?? ($isYouTube ? 'short' : 'screenshot'))),
            ];
        }

        $submittedAt = AppTime::now();
        $storageDirectory = dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'storage'
            . DIRECTORY_SEPARATOR . ($isYouTube ? 'daily-activity-videos' : 'daily-activity-screenshots');
        if (!is_dir($storageDirectory) && !mkdir($storageDirectory, 0700, true) && !is_dir($storageDirectory)) {
            throw new RuntimeException($isYouTube
                ? 'Daily activity video storage could not be created.'
                : 'Daily activity screenshot storage could not be created.');
        }

        $savedPaths = [];
        $this->database->beginTransaction();
        try {
            $activityInsert = $this->database->prepare(
                "INSERT INTO daily_activities (user_id, platform, submitted_at, status)
                 VALUES (:user_id, :platform, :submitted_at, 'pending')"
            );
            $activityInsert->execute([
                'user_id' => $userId,
                'platform' => $platform,
                'submitted_at' => $submittedAt->format('Y-m-d H:i:s'),
            ]);
            $activityId = (int) $this->database->lastInsertId();

            $mediaTable = $isYouTube ? 'daily_activity_videos' : 'daily_activity_screenshots';
            $mediaInsert = $this->database->prepare(
                "INSERT INTO {$mediaTable}
                    (activity_id, file_name, original_name, mime_type, file_size)
                 VALUES (:activity_id, :file_name, :original_name, :mime_type, :file_size)"
            );
            foreach ($validatedFiles as $file) {
                $extension = match ($file['mime_type']) {
                    'image/jpeg' => 'jpg',
                    'image/png' => 'png',
                    'image/webp' => 'webp',
                    'video/mp4' => 'mp4',
                    'video/webm' => 'webm',
                };
                $fileName = bin2hex(random_bytes(24)) . '.' . $extension;
                $destination = $storageDirectory . DIRECTORY_SEPARATOR . $fileName;
                if (!move_uploaded_file($file['tmp_name'], $destination)) {
                    throw new RuntimeException($isYouTube
                        ? 'A YouTube Short video could not be saved. Please try uploading again.'
                        : 'A screenshot could not be saved. Please try uploading again.');
                }
                $savedPaths[] = $destination;

                $mediaInsert->execute([
                    'activity_id' => $activityId,
                    'file_name' => $fileName,
                    'original_name' => $file['original_name'],
                    'mime_type' => $file['mime_type'],
                    'file_size' => $file['file_size'],
                ]);
            }
            $this->database->commit();
        } catch (Throwable $error) {
            if ($this->database->inTransaction()) {
                $this->database->rollBack();
            }
            foreach ($savedPaths as $savedPath) {
                if (is_file($savedPath) && !unlink($savedPath)) {
                    error_log('Could not remove a daily activity upload after a failed submission.');
                }
            }
            throw $error;
        }

        return [
            'id' => $activityId,
            'platform' => $platform,
            'submittedAt' => $submittedAt->format('Y-m-d H:i:s'),
            'status' => 'pending',
            $isYouTube ? 'videoCount' : 'screenshotCount' => count($validatedFiles),
        ];
    }

    public function verify(int $activityId, int $reviewerId): void
    {
        $this->decide($activityId, $reviewerId, 'approve', null);
    }

    public function decide(int $activityId, int $reviewerId, string $decision, ?string $reason): void
    {
        if (!in_array($decision, ['approve', 'reject'], true)) {
            throw new InvalidArgumentException('Choose approve or reject for this activity.');
        }

        $reason = $reason === null ? '' : trim($reason);
        if ($decision === 'reject' && preg_match('/^.{1,500}$/us', $reason) !== 1) {
            throw new InvalidArgumentException('Enter a rejection reason (up to 500 characters).');
        }
        if ($decision === 'approve') {
            $this->approveWithBonus($activityId, $reviewerId);
            return;
        }

        $now = AppTime::now()->format('Y-m-d H:i:s');
        $statement = $this->database->prepare(
            "UPDATE daily_activities
             SET status = 'rejected', verified_by = :reviewed_by,
                 reviewed_at = :reviewed_at,
                 rejection_reason = :rejection_reason
             WHERE id = :id AND status = 'pending'"
        );
        $statement->execute([
            'reviewed_by' => $reviewerId,
            'reviewed_at' => $now,
            'rejection_reason' => $reason,
            'id' => $activityId,
        ]);
        if ($statement->rowCount() === 1) {
            return;
        }

        $exists = $this->database->prepare('SELECT status FROM daily_activities WHERE id = :id');
        $exists->execute(['id' => $activityId]);
        $status = $exists->fetchColumn();
        if ($status === false) {
            throw new DomainException('Daily activity submission not found.');
        }
        throw new DomainException('This daily activity has already been reviewed.');
    }

    private function approveWithBonus(int $activityId, int $reviewerId): void
    {
        $now = AppTime::now()->format('Y-m-d H:i:s');
        $this->database->beginTransaction();
        try {
            $activityQuery = $this->database->prepare(
                'SELECT user_id, platform, submitted_at, status
                 FROM daily_activities WHERE id = :id FOR UPDATE'
            );
            $activityQuery->execute(['id' => $activityId]);
            $activity = $activityQuery->fetch(PDO::FETCH_ASSOC);
            if (!is_array($activity)) {
                throw new DomainException('Daily activity submission not found.');
            }
            if ($activity['status'] !== 'pending') {
                throw new DomainException('This daily activity has already been reviewed.');
            }

            $userLock = $this->database->prepare('SELECT id FROM users WHERE id = :id FOR UPDATE');
            $userLock->execute(['id' => $activity['user_id']]);
            if ($userLock->fetchColumn() === false) {
                throw new DomainException('The employee for this daily activity could not be found.');
            }

            $rateQuery = $this->database->prepare(
                'SELECT amount FROM daily_activity_bonus_rates WHERE platform = :platform'
            );
            $rateQuery->execute(['platform' => $activity['platform']]);
            $rate = $rateQuery->fetchColumn();
            if ($rate === false) {
                throw new RuntimeException('A daily activity platform rate is missing. Run the database schema setup.');
            }

            $dailyCap = $this->database->query(
                'SELECT daily_cap_amount FROM daily_activity_bonus_limits WHERE id = 1'
            )->fetchColumn();
            if ($dailyCap === false) {
                throw new RuntimeException('Daily activity bonus settings are missing. Run the database schema setup.');
            }

            $submittedDate = substr((string) $activity['submitted_at'], 0, 10);
            $earnedSoFar = $this->verifiedBonusTotal((int) $activity['user_id'], $submittedDate);
            $bonusAmount = round(min((float) $rate, max(0.0, (float) $dailyCap - $earnedSoFar)), 2);

            $update = $this->database->prepare(
                "UPDATE daily_activities
                 SET status = 'verified', verified_by = :reviewer_id,
                     verified_at = :verified_at, reviewed_at = :reviewed_at,
                     rejection_reason = NULL, bonus_amount = :bonus_amount
                 WHERE id = :id AND status = 'pending'"
            );
            $update->execute([
                'reviewer_id' => $reviewerId,
                'verified_at' => $now,
                'reviewed_at' => $now,
                'bonus_amount' => $bonusAmount,
                'id' => $activityId,
            ]);
            if ($update->rowCount() !== 1) {
                throw new DomainException('This daily activity has already been reviewed.');
            }

            $this->database->commit();
        } catch (\Throwable $error) {
            if ($this->database->inTransaction()) {
                $this->database->rollBack();
            }
            throw $error;
        }
    }

    private function verifiedBonusTotal(int $userId, string $activityDate): float
    {
        $statement = $this->database->prepare(
            'SELECT a.platform, a.bonus_amount, COUNT(s.id) AS screenshot_count
             FROM daily_activities a
             LEFT JOIN daily_activity_screenshots s ON s.activity_id = a.id
             WHERE a.user_id = :user_id
               AND a.status = \'verified\'
               AND DATE(a.submitted_at) = :activity_date
             GROUP BY a.id
             ORDER BY a.submitted_at ASC, a.id ASC'
        );
        $statement->execute(['user_id' => $userId, 'activity_date' => $activityDate]);
        $total = 0.0;
        $legacySocialTotal = 0.0;
        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
            if ($row['bonus_amount'] !== null) {
                $total += (float) $row['bonus_amount'];
            } elseif (in_array($row['platform'], ['Instagram', 'Facebook', 'WhatsApp Status'], true)) {
                $legacyReward = min(6.0 - $legacySocialTotal, (int) $row['screenshot_count'] * 2.0);
                $legacySocialTotal += $legacyReward;
                $total += $legacyReward;
            }
        }

        return round($total, 2);
    }

    public function screenshot(int $screenshotId, int $activityId, ?int $userId): array
    {
        $sql = 'SELECT s.file_name, s.original_name, s.mime_type
                FROM daily_activity_screenshots s
                JOIN daily_activities a ON a.id = s.activity_id
                WHERE s.id = :screenshot_id AND a.id = :activity_id';
        $parameters = ['screenshot_id' => $screenshotId, 'activity_id' => $activityId];
        if ($userId !== null) {
            $sql .= ' AND a.user_id = :user_id';
            $parameters['user_id'] = $userId;
        }
        $statement = $this->database->prepare($sql);
        $statement->execute($parameters);
        $screenshot = $statement->fetch(PDO::FETCH_ASSOC);
        if (!is_array($screenshot)) {
            throw new DomainException('Screenshot not found or you do not have permission to view it.');
        }

        $path = dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'storage'
            . DIRECTORY_SEPARATOR . 'daily-activity-screenshots'
            . DIRECTORY_SEPARATOR . $screenshot['file_name'];
        if (!is_file($path) || !is_readable($path)) {
            throw new RuntimeException('A submitted screenshot could not be read from private storage.');
        }

        return [
            'path' => $path,
            'mimeType' => (string) $screenshot['mime_type'],
            'name' => (string) $screenshot['original_name'],
        ];
    }

    public function video(int $videoId, int $activityId, ?int $userId): array
    {
        $sql = 'SELECT v.file_name, v.original_name, v.mime_type
                FROM daily_activity_videos v
                JOIN daily_activities a ON a.id = v.activity_id
                WHERE v.id = :video_id AND a.id = :activity_id';
        $parameters = ['video_id' => $videoId, 'activity_id' => $activityId];
        if ($userId !== null) {
            $sql .= ' AND a.user_id = :user_id';
            $parameters['user_id'] = $userId;
        }
        $statement = $this->database->prepare($sql);
        $statement->execute($parameters);
        $video = $statement->fetch(PDO::FETCH_ASSOC);
        if (!is_array($video)) {
            throw new DomainException('Video not found or you do not have permission to view it.');
        }

        $path = dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'storage'
            . DIRECTORY_SEPARATOR . 'daily-activity-videos'
            . DIRECTORY_SEPARATOR . $video['file_name'];
        if (!is_file($path) || !is_readable($path)) {
            throw new RuntimeException('A submitted video could not be read from private storage.');
        }

        return [
            'path' => $path,
            'mimeType' => (string) $video['mime_type'],
            'name' => (string) $video['original_name'],
        ];
    }

    private function normalizeUploads(mixed $upload, int $maxFiles, string $kind): array
    {
        if (!is_array($upload) || !isset($upload['name'], $upload['tmp_name'], $upload['error'], $upload['size'])) {
            throw new InvalidArgumentException($kind === 'video'
                ? 'Upload at least one YouTube Short video; videos are required.'
                : 'Upload at least one screenshot; screenshots are required.');
        }
        $names = is_array($upload['name']) ? $upload['name'] : [$upload['name']];
        if ($names === [] || count($names) > $maxFiles) {
            throw new InvalidArgumentException($kind === 'video'
                ? 'Upload between 1 and 2 YouTube Short videos for each activity.'
                : 'Upload between 1 and 5 screenshots for each activity.');
        }

        $files = [];
        foreach ($names as $index => $name) {
            $files[] = [
                'name' => $name,
                'tmp_name' => is_array($upload['tmp_name']) ? ($upload['tmp_name'][$index] ?? null) : $upload['tmp_name'],
                'error' => is_array($upload['error']) ? ($upload['error'][$index] ?? UPLOAD_ERR_NO_FILE) : $upload['error'],
                'size' => is_array($upload['size']) ? ($upload['size'][$index] ?? 0) : $upload['size'],
            ];
        }

        return $files;
    }

    private function safeOriginalName(string $name): string
    {
        $name = basename(str_replace('\\', '/', $name));
        $name = preg_replace('/[\x00-\x1F\x7F]/u', '', $name) ?? '';
        $name = trim($name);
        if ($name === '') {
            return 'screenshot';
        }

        if (preg_match('/^.{1,180}/us', $name, $matches) === 1) {
            return $matches[0];
        }

        return 'screenshot';
    }

    private function uploadErrorMessage(int $error, bool $isVideo): string
    {
        return match ($error) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => $isVideo
                ? 'A video exceeds the server upload size limit.'
                : 'A screenshot exceeds the server upload size limit.',
            UPLOAD_ERR_PARTIAL => $isVideo
                ? 'A video upload was interrupted. Try again.'
                : 'A screenshot upload was interrupted. Try again.',
            UPLOAD_ERR_NO_FILE => $isVideo
                ? 'Upload at least one YouTube Short video; videos are required.'
                : 'Upload at least one screenshot; screenshots are required.',
            default => $isVideo
                ? 'A video could not be uploaded. Please try again.'
                : 'A screenshot could not be uploaded. Please try again.',
        };
    }
}
