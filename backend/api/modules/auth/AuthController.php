<?php

declare(strict_types=1);

namespace Arowin\Modules\Auth;

use PDO;
use InvalidArgumentException;
use RuntimeException;

final class AuthController
{
    private const MAX_PROFILE_IMAGE_BYTES = 5_000_000;

    public function __construct(private readonly PDO $database)
    {
    }

    public function currentUser(int $userId): ?array
    {
        $statement = $this->database->prepare(
            'SELECT employee.id, employee.employee_id, employee.name, employee.email, employee.role,
                    employee.mobile, employee.date_of_birth, employee.address,
                    employee.profile_image_path, salary.joining_date
             FROM users AS employee
             LEFT JOIN employee_salaries AS salary ON salary.user_id = employee.id
             WHERE employee.id = :id AND employee.is_active = 1
             LIMIT 1'
        );
        $statement->execute(['id' => $userId]);
        $user = $statement->fetch();
        if (!is_array($user)) {
            return null;
        }

        return [
            'id' => (int) $user['id'],
            'employeeId' => (string) $user['employee_id'],
            'name' => $user['name'],
            'email' => $user['email'],
            'role' => $user['role'],
            'mobile' => (string) $user['mobile'],
            'dob' => (string) $user['date_of_birth'],
            'address' => (string) $user['address'],
            'profileImage' => $user['profile_image_path'] === null ? null : (string) $user['profile_image_path'],
            'joiningDate' => $user['joining_date'] === null ? null : (string) $user['joining_date'],
        ];
    }

    public function updateProfileImage(int $userId, mixed $upload): string
    {
        if (!is_array($upload) || !isset($upload['error'])) {
            throw new InvalidArgumentException('Choose a profile photo to upload.');
        }
        if ((int) $upload['error'] !== UPLOAD_ERR_OK) {
            throw new InvalidArgumentException(match ((int) $upload['error']) {
                UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'The profile photo must be smaller than 5 MB.',
                UPLOAD_ERR_PARTIAL => 'The profile photo upload was incomplete. Please try again.',
                UPLOAD_ERR_NO_FILE => 'Choose a profile photo to upload.',
                default => 'The profile photo could not be uploaded. Please try again.',
            });
        }

        $temporaryPath = $upload['tmp_name'] ?? null;
        if (!is_string($temporaryPath) || !is_uploaded_file($temporaryPath)) {
            throw new InvalidArgumentException('Choose a valid profile photo file.');
        }
        $fileSize = (int) ($upload['size'] ?? 0);
        if ($fileSize < 1 || $fileSize > self::MAX_PROFILE_IMAGE_BYTES) {
            throw new InvalidArgumentException('The profile photo must be smaller than 5 MB.');
        }

        $fileInfo = new \finfo(FILEINFO_MIME_TYPE);
        $mimeType = $fileInfo->file($temporaryPath);
        $imageInfo = getimagesize($temporaryPath);
        $extensions = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
        ];
        if (!is_string($mimeType)
            || !isset($extensions[$mimeType])
            || !is_array($imageInfo)
            || ($imageInfo['mime'] ?? '') !== $mimeType
            || (int) ($imageInfo[0] ?? 0) < 1
            || (int) ($imageInfo[1] ?? 0) < 1
            || (int) $imageInfo[0] * (int) $imageInfo[1] > 20_000_000) {
            throw new InvalidArgumentException('Profile photos must be valid JPEG, PNG, or WebP images.');
        }

        $storageDirectory = dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'storage'
            . DIRECTORY_SEPARATOR . 'profile-images';
        if (!is_dir($storageDirectory) && !mkdir($storageDirectory, 0700, true) && !is_dir($storageDirectory)) {
            throw new RuntimeException('Profile photo storage could not be created.');
        }

        $fileName = bin2hex(random_bytes(24)) . '.' . $extensions[$mimeType];
        $destination = $storageDirectory . DIRECTORY_SEPARATOR . $fileName;
        if (!move_uploaded_file($temporaryPath, $destination)) {
            throw new RuntimeException('The profile photo could not be saved. Please try again.');
        }

        $currentImageQuery = $this->database->prepare('SELECT profile_image_path FROM users WHERE id = :id LIMIT 1');
        $currentImageQuery->execute(['id' => $userId]);
        $previousFileName = $currentImageQuery->fetchColumn();
        if ($previousFileName === false) {
            if (!unlink($destination)) {
                error_log('Could not remove a profile photo after its employee record was not found.');
            }
            throw new RuntimeException('The employee profile could not be found.');
        }

        try {
            $update = $this->database->prepare(
                'UPDATE users SET profile_image_path = :profile_image_path WHERE id = :id'
            );
            $update->execute(['profile_image_path' => $fileName, 'id' => $userId]);
        } catch (\Throwable $error) {
            if (!unlink($destination)) {
                error_log('Could not remove a profile photo after its database update failed.');
            }
            throw $error;
        }

        if (is_string($previousFileName) && preg_match('/^[a-f0-9]{48}\.(jpg|png|webp)$/', $previousFileName) === 1) {
            $previousPath = $storageDirectory . DIRECTORY_SEPARATOR . $previousFileName;
            if (is_file($previousPath) && !unlink($previousPath)) {
                error_log('Could not remove a replaced employee profile photo.');
            }
        }

        return $fileName;
    }

    public function profileImage(int $userId): ?array
    {
        $statement = $this->database->prepare('SELECT profile_image_path FROM users WHERE id = :id LIMIT 1');
        $statement->execute(['id' => $userId]);
        $fileName = $statement->fetchColumn();
        if ($fileName === false) {
            throw new RuntimeException('The employee profile could not be found.');
        }
        if (!is_string($fileName) || $fileName === '') {
            return null;
        }
        if (preg_match('/^[a-f0-9]{48}\.(jpg|png|webp)$/', $fileName) !== 1) {
            throw new RuntimeException('The saved profile photo record is invalid.');
        }

        $path = dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'storage'
            . DIRECTORY_SEPARATOR . 'profile-images' . DIRECTORY_SEPARATOR . $fileName;
        if (!is_file($path) || !is_readable($path)) {
            throw new RuntimeException('The saved profile photo is unavailable.');
        }

        return [
            'path' => $path,
            'mimeType' => match (pathinfo($fileName, PATHINFO_EXTENSION)) {
                'jpg' => 'image/jpeg',
                'png' => 'image/png',
                'webp' => 'image/webp',
            },
        ];
    }

    public function login(array $payload): array
    {
        $identifier = trim((string) ($payload['username'] ?? $payload['email'] ?? ''));
        $password = (string) ($payload['password'] ?? '');

        if ($identifier === '' || $password === '') {
            return [
                'success' => false,
                'message' => 'Email or Employee ID and password are required.',
            ];
        }

        $isEmployeeId = preg_match('/^[0-9]{6}$/', $identifier) === 1;
        $statement = $this->database->prepare(
            $isEmployeeId
                ? "SELECT employee.id, employee.employee_id, employee.name, employee.email, employee.password_hash,
                          employee.role, employee.mobile, employee.date_of_birth, employee.address,
                          employee.profile_image_path, salary.joining_date
                   FROM users AS employee
                   LEFT JOIN employee_salaries AS salary ON salary.user_id = employee.id
                   WHERE employee.employee_id = :identifier
                     AND employee.role IN ('team_leader', 'telecaller')
                     AND employee.is_active = 1
                   LIMIT 1"
                : 'SELECT employee.id, employee.employee_id, employee.name, employee.email, employee.password_hash,
                          employee.role, employee.mobile, employee.date_of_birth, employee.address,
                          employee.profile_image_path, salary.joining_date
                   FROM users AS employee
                   LEFT JOIN employee_salaries AS salary ON salary.user_id = employee.id
                   WHERE employee.email = :identifier AND employee.is_active = 1
                   LIMIT 1'
        );
        $statement->execute(['identifier' => $isEmployeeId ? $identifier : strtolower($identifier)]);
        $user = $statement->fetch();

        if (!is_array($user) || !password_verify($password, $user['password_hash'])) {
            return [
                'success' => false,
                'message' => 'Invalid email or Employee ID and password.',
            ];
        }

        session_regenerate_id(true);
        $_SESSION['user'] = [
            'id' => (int) $user['id'],
            'employeeId' => (string) $user['employee_id'],
            'name' => $user['name'],
            'email' => $user['email'],
            'role' => $user['role'],
            'mobile' => (string) $user['mobile'],
            'dob' => (string) $user['date_of_birth'],
            'address' => (string) $user['address'],
            'profileImage' => $user['profile_image_path'] === null ? null : (string) $user['profile_image_path'],
            'joiningDate' => $user['joining_date'] === null ? null : (string) $user['joining_date'],
        ];

        return [
            'success' => true,
            'message' => 'Login successful.',
            'user' => $_SESSION['user'],
        ];
    }

    public static function logout(): void
    {
        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $parameters = session_get_cookie_params();
            setcookie(session_name(), '', [
                'expires' => time() - 42000,
                'path' => $parameters['path'],
                'domain' => $parameters['domain'],
                'secure' => $parameters['secure'],
                'httponly' => $parameters['httponly'],
                'samesite' => $parameters['samesite'] ?? 'Lax',
            ]);
        }

        session_destroy();
    }
}
