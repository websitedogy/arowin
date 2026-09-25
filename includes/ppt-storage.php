<?php
/**
 * PPT storage helpers — current file + admin change history.
 */
declare(strict_types=1);

function arowin_ppt_dir(): string
{
    return dirname(__DIR__) . '/storage/ppt';
}

function arowin_ppt_meta_file(): string
{
    return arowin_ppt_dir() . '/current.json';
}

function arowin_ppt_history_file(): string
{
    return arowin_ppt_dir() . '/history.json';
}

function arowin_ppt_ensure_dir(): void
{
    $dir = arowin_ppt_dir();
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
}

/**
 * @return array{original_name:string,stored_name:string,uploaded_by:string,uploaded_at:string,size:int}|null
 */
function arowin_ppt_current(): ?array
{
    $metaFile = arowin_ppt_meta_file();
    if (!is_file($metaFile)) {
        return null;
    }
    $meta = json_decode((string) file_get_contents($metaFile), true);
    if (!is_array($meta) || empty($meta['stored_name'])) {
        return null;
    }
    $stored = basename((string) $meta['stored_name']);
    if ($stored === '' || !is_file(arowin_ppt_dir() . '/' . $stored)) {
        return null;
    }
    return [
        'original_name' => (string) ($meta['original_name'] ?? 'Presentation'),
        'stored_name'   => $stored,
        'uploaded_by'   => (string) ($meta['uploaded_by'] ?? ''),
        'uploaded_at'   => (string) ($meta['uploaded_at'] ?? ''),
        'size'          => (int) ($meta['size'] ?? 0),
    ];
}

/**
 * @return list<array<string,mixed>>
 */
function arowin_ppt_history(): array
{
    $file = arowin_ppt_history_file();
    if (!is_file($file)) {
        // Seed from current file if history was never written.
        $current = arowin_ppt_current();
        if ($current === null) {
            return [];
        }
        $seed = [[
            'id'            => 'seed-' . md5($current['stored_name'] . $current['uploaded_at']),
            'action'        => 'uploaded',
            'original_name' => $current['original_name'],
            'stored_name'   => $current['stored_name'],
            'uploaded_by'   => $current['uploaded_by'],
            'uploaded_at'   => $current['uploaded_at'],
            'size'          => $current['size'],
        ]];
        arowin_ppt_history_save($seed);
        return $seed;
    }

    $rows = json_decode((string) file_get_contents($file), true);
    if (!is_array($rows)) {
        return [];
    }

    $out = [];
    foreach ($rows as $row) {
        if (is_array($row)) {
            $out[] = $row;
        }
    }
    return $out;
}

/**
 * @param list<array<string,mixed>> $rows
 */
function arowin_ppt_history_save(array $rows): void
{
    arowin_ppt_ensure_dir();
    file_put_contents(
        arowin_ppt_history_file(),
        json_encode(array_values($rows), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
    );
}

/**
 * @param array<string,mixed> $entry
 */
function arowin_ppt_history_prepend(array $entry): void
{
    $rows = arowin_ppt_history();
    array_unshift($rows, $entry);
    // Keep a reasonable trail (metadata only — old files are deleted).
    if (count($rows) > 200) {
        $rows = array_slice($rows, 0, 200);
    }
    arowin_ppt_history_save($rows);
}

/**
 * @return array{ok:bool,error?:string}
 */
function arowin_ppt_save_upload(array $file, string $adminUsername): array
{
    arowin_ppt_ensure_dir();

    if ((int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return ['ok' => false, 'error' => 'Choose a PDF file to upload.'];
    }
    if ((int) ($file['error'] ?? 1) !== UPLOAD_ERR_OK) {
        return ['ok' => false, 'error' => 'Upload failed. Try again.'];
    }

    $orig = (string) ($file['name'] ?? 'presentation');
    $ext = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
    if ($ext !== 'pdf') {
        return ['ok' => false, 'error' => 'Upload a PDF only — members view it in the browser.'];
    }
    if ((int) ($file['size'] ?? 0) > 25 * 1024 * 1024) {
        return ['ok' => false, 'error' => 'File is too large (max 25 MB).'];
    }

    $stored = 'arowin-ppt-' . date('Ymd-His') . '.pdf';
    $dest = arowin_ppt_dir() . '/' . $stored;
    if (!move_uploaded_file((string) $file['tmp_name'], $dest)) {
        return ['ok' => false, 'error' => 'Could not save uploaded file.'];
    }

    $hadPrevious = false;
    $previous = arowin_ppt_current();
    if ($previous !== null) {
        $hadPrevious = true;
        // Keep previous PDF on disk so admin history can still preview it.
    }

    $meta = [
        'original_name' => $orig,
        'stored_name'   => $stored,
        'uploaded_by'   => $adminUsername,
        'uploaded_at'   => date('Y-m-d H:i:s'),
        'size'          => (int) ($file['size'] ?? 0),
    ];
    file_put_contents(
        arowin_ppt_meta_file(),
        json_encode($meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
    );

    arowin_ppt_history_prepend([
        'id'            => uniqid('ppt_', true),
        'action'        => $hadPrevious ? 'replaced' : 'uploaded',
        'original_name' => $meta['original_name'],
        'stored_name'   => $meta['stored_name'],
        'uploaded_by'   => $meta['uploaded_by'],
        'uploaded_at'   => $meta['uploaded_at'],
        'size'          => $meta['size'],
        'replaced'      => $hadPrevious
            ? (string) ($previous['original_name'] ?? '')
            : '',
    ]);

    return ['ok' => true];
}

/**
 * @return array{ok:bool,error?:string}
 */
function arowin_ppt_history_edit(string $id, string $originalName): array
{
    $id = trim($id);
    $originalName = trim($originalName);
    if ($id === '') {
        return ['ok' => false, 'error' => 'Invalid history item.'];
    }
    if ($originalName === '') {
        return ['ok' => false, 'error' => 'File name cannot be empty.'];
    }
    // Keep .pdf extension for clarity when renaming.
    if (!preg_match('/\.pdf$/i', $originalName)) {
        $originalName .= '.pdf';
    }

    $rows = arowin_ppt_history();
    $found = false;
    $stored = '';
    foreach ($rows as &$row) {
        if ((string) ($row['id'] ?? '') !== $id) {
            continue;
        }
        $row['original_name'] = $originalName;
        $stored = (string) ($row['stored_name'] ?? '');
        $found = true;
        break;
    }
    unset($row);

    if (!$found) {
        return ['ok' => false, 'error' => 'History item not found.'];
    }

    arowin_ppt_history_save($rows);

    $current = arowin_ppt_current();
    if ($current !== null && $stored !== '' && $current['stored_name'] === $stored) {
        $meta = $current;
        $meta['original_name'] = $originalName;
        file_put_contents(
            arowin_ppt_meta_file(),
            json_encode($meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );
    }

    return ['ok' => true];
}

/**
 * Delete a history row. If it is the live file, also remove it for members.
 *
 * @return array{ok:bool,error?:string}
 */
function arowin_ppt_history_delete(string $id): array
{
    $id = trim($id);
    if ($id === '') {
        return ['ok' => false, 'error' => 'Invalid history item.'];
    }

    $rows = arowin_ppt_history();
    $target = null;
    $next = [];
    foreach ($rows as $row) {
        if ((string) ($row['id'] ?? '') === $id) {
            $target = $row;
            continue;
        }
        $next[] = $row;
    }

    if ($target === null) {
        return ['ok' => false, 'error' => 'History item not found.'];
    }

    $stored = basename((string) ($target['stored_name'] ?? ''));
    $current = arowin_ppt_current();
    if ($stored !== '') {
        $path = arowin_ppt_dir() . '/' . $stored;
        if (is_file($path)) {
            @unlink($path);
        }
    }
    if ($current !== null && $stored !== '' && $current['stored_name'] === $stored) {
        if (is_file(arowin_ppt_meta_file())) {
            @unlink(arowin_ppt_meta_file());
        }
    }

    arowin_ppt_history_save($next);
    return ['ok' => true];
}

/**
 * Resolve a history PDF path for admin preview.
 *
 * @return array{path:string,original_name:string}|null
 */
function arowin_ppt_file_for_history_id(string $id): ?array
{
    $id = trim($id);
    if ($id === '') {
        return null;
    }
    foreach (arowin_ppt_history() as $row) {
        if ((string) ($row['id'] ?? '') !== $id) {
            continue;
        }
        $stored = basename((string) ($row['stored_name'] ?? ''));
        if ($stored === '') {
            return null;
        }
        $path = arowin_ppt_dir() . '/' . $stored;
        if (!is_file($path)) {
            return null;
        }
        return [
            'path' => $path,
            'original_name' => (string) ($row['original_name'] ?? $stored),
        ];
    }
    return null;
}
