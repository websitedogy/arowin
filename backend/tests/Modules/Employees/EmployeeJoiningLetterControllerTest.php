<?php

declare(strict_types=1);

use Arowin\Modules\Employees\EmployeeJoiningLetterController;
use PHPUnit\Framework\TestCase;

final class EmployeeJoiningLetterControllerTest extends TestCase
{
    public function testJoiningLetterCanBeReadOnlyForItsEmployee(): void
    {
        $database = new PDO('sqlite::memory:');
        $database->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $database->exec(
            "CREATE TABLE employee_joining_letters (
                user_id INTEGER PRIMARY KEY,
                file_path TEXT NOT NULL,
                original_name TEXT NOT NULL,
                mime_type TEXT NOT NULL,
                file_size INTEGER NOT NULL,
                uploaded_at TEXT NOT NULL
            )"
        );
        $storageRoot = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'arowin-joining-letter-' . bin2hex(random_bytes(6));
        $userDirectory = $storageRoot . DIRECTORY_SEPARATOR . '12';
        self::assertTrue(mkdir($userDirectory, 0700, true));

        $fileName = str_repeat('a', 48) . '.pdf';
        $filePath = $userDirectory . DIRECTORY_SEPARATOR . $fileName;
        self::assertSame(5, file_put_contents($filePath, '%PDF-'));
        $insert = $database->prepare(
            'INSERT INTO employee_joining_letters
                (user_id, file_path, original_name, mime_type, file_size, uploaded_at)
             VALUES (12, :file_path, :original_name, :mime_type, :file_size, :uploaded_at)'
        );
        $insert->execute([
            'file_path' => $fileName,
            'original_name' => 'Joining Letter.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 5,
            'uploaded_at' => '2026-11-17 10:30:00',
        ]);

        try {
            $controller = new EmployeeJoiningLetterController($database, $storageRoot);
            self::assertSame([
                'name' => 'Joining Letter.pdf',
                'mimeType' => 'application/pdf',
                'size' => 5,
                'uploadedAt' => '2026-11-17 10:30:00',
            ], $controller->get(12));
            self::assertSame($filePath, $controller->document(12)['path']);
            self::assertNull($controller->get(13));
            self::assertNull($controller->document(13));
        } finally {
            unlink($filePath);
            rmdir($userDirectory);
            rmdir($storageRoot);
        }
    }

    public function testJoiningLetterRejectsInvalidStoredPath(): void
    {
        $database = new PDO('sqlite::memory:');
        $database->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $database->exec(
            "CREATE TABLE employee_joining_letters (
                user_id INTEGER PRIMARY KEY,
                file_path TEXT NOT NULL,
                original_name TEXT NOT NULL,
                mime_type TEXT NOT NULL,
                file_size INTEGER NOT NULL,
                uploaded_at TEXT NOT NULL
            )"
        );
        $database->exec(
            "INSERT INTO employee_joining_letters
                (user_id, file_path, original_name, mime_type, file_size, uploaded_at)
             VALUES (12, '../private.pdf', 'Joining Letter.pdf', 'application/pdf', 5, '2026-11-17 10:30:00')"
        );

        $this->expectException(RuntimeException::class);
        (new EmployeeJoiningLetterController($database, sys_get_temp_dir()))->document(12);
    }
}
