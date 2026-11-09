<?php

declare(strict_types=1);

namespace Arowin\Modules\Sales;

interface OtpChallengeStore
{
    public function getLastSentAt(): int;

    public function setLastSentAt(int $timestamp): void;

    public function getChallenge(): ?array;

    public function setChallenge(array $challenge): void;

    public function clearChallenge(): void;

    public function getVerified(): ?array;

    public function setVerified(array $verified): void;

    public function clearVerified(): void;
}
