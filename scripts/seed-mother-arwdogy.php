<?php
/**
 * Ensure mother ID ARWDOGY exists with the configured password and owns legacy AROWIN trees.
 */
declare(strict_types=1);

require dirname(__DIR__) . '/includes/bootstrap.php';

$username = 'ARWDOGY';
$password = 'Zealstar_02';
$hash = password_hash($password, PASSWORD_DEFAULT);

$pdo = arowin_db();

$exists = $pdo->prepare('SELECT id FROM node_registrations WHERE UPPER(username) = :u LIMIT 1');
$exists->execute([':u' => $username]);
$id = (int) ($exists->fetchColumn() ?: 0);

if ($id > 0) {
    $upd = $pdo->prepare(
        'UPDATE node_registrations
         SET password_hash = :h, full_name = :n, status = \'active\', consent = 1
         WHERE id = :id LIMIT 1'
    );
    $upd->execute([
        ':h'  => $hash,
        ':n'  => 'Arowin Mother Node',
        ':id' => $id,
    ]);
    echo "Updated mother account {$username} (id={$id})\n";
} else {
    $ins = $pdo->prepare(
        'INSERT INTO node_registrations
            (full_name, email, phone, username, password_hash, sponsor_id, preferred_leg, consent, status)
         VALUES
            (:n, NULL, :p, :u, :h, NULL, \'left\', 1, \'active\')'
    );
    $ins->execute([
        ':n' => 'Arowin Mother Node',
        ':p' => '0000000000',
        ':u' => $username,
        ':h' => $hash,
    ]);
    $id = (int) $pdo->lastInsertId();
    echo "Created mother account {$username} (id={$id})\n";
}

$migrated = $pdo->exec(
    "UPDATE node_registrations SET sponsor_id = 'ARWDOGY' WHERE UPPER(sponsor_id) = 'AROWIN'"
);
echo "Migrated sponsor_id AROWIN → ARWDOGY: {$migrated} row(s)\n";

$placed = $pdo->exec(
    "UPDATE node_registrations
     SET binary_parent_id = 'ARWDOGY', binary_placement = 'left'
     WHERE UPPER(sponsor_id) = 'ARWDOGY'
       AND (binary_parent_id IS NULL OR binary_parent_id = '')
       AND UPPER(username) <> 'ARWDOGY'"
);
echo "Placed orphan roots under ARWDOGY: {$placed} row(s)\n";

$user = arowin_authenticate($username, $password);
echo $user
    ? "Auth OK — dashboard login ready for {$username}\n"
    : "Auth FAILED — check password_hash\n";
exit($user ? 0 : 1);
