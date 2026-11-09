<?php

declare(strict_types=1);

namespace Arowin\Modules\Sales;

final class PhpSessionOtpChallengeStore implements OtpChallengeStore
{
    public function getLastSentAt(): int
    {
        return (int) ($_SESSION['unlock_sales_otp_last_sent_at'] ?? 0);
    }

    public function setLastSentAt(int $timestamp): void
    {
        $_SESSION['unlock_sales_otp_last_sent_at'] = $timestamp;
    }

    public function getChallenge(): ?array
    {
        $challenge = $_SESSION['unlock_sales_otp'] ?? null;
        return is_array($challenge) ? $challenge : null;
    }

    public function setChallenge(array $challenge): void
    {
        $_SESSION['unlock_sales_otp'] = $challenge;
    }

    public function clearChallenge(): void
    {
        unset($_SESSION['unlock_sales_otp']);
    }

    public function getVerified(): ?array
    {
        $verified = $_SESSION['unlock_sales_otp_verified'] ?? null;
        return is_array($verified) ? $verified : null;
    }

    public function setVerified(array $verified): void
    {
        $_SESSION['unlock_sales_otp_verified'] = $verified;
    }

    public function clearVerified(): void
    {
        unset($_SESSION['unlock_sales_otp_verified']);
    }
}
