<?php
/**
 * Contact inquiry repository
 */
declare(strict_types=1);

function arowin_save_contact_inquiry(array $data): int
{
    $sql = 'INSERT INTO contact_inquiries
        (first_name, last_name, email, phone, organization, topic, subject, message, consent, ip_address, user_agent)
        VALUES
        (:first_name, :last_name, :email, :phone, :organization, :topic, :subject, :message, :consent, :ip_address, :user_agent)';

    $stmt = arowin_db()->prepare($sql);
    $stmt->execute([
        ':first_name'   => $data['first_name'],
        ':last_name'    => $data['last_name'],
        ':email'        => $data['email'],
        ':phone'        => $data['phone'] ?: null,
        ':organization' => $data['organization'] ?: null,
        ':topic'        => $data['topic'],
        ':subject'      => $data['subject'],
        ':message'      => $data['message'],
        ':consent'      => !empty($data['consent']) ? 1 : 0,
        ':ip_address'   => $data['ip_address'] ?? null,
        ':user_agent'   => $data['user_agent'] ?? null,
    ]);

    return (int) arowin_db()->lastInsertId();
}
