<?php

declare(strict_types=1);

namespace Arowin\Modules\Sales;

use Arowin\ClockInterface;
use Arowin\OtpGenerator;
use Arowin\SystemClock;
use DomainException;
use PDO;

final class UnlockOtpController
{
    private const OTP_LIFETIME_SECONDS = 600;
    private const RESEND_WAIT_SECONDS = 60;
    private const MAX_ATTEMPTS = 5;

    public function __construct(
        private readonly PDO $database,
        ?UserRepository $userRepository = null,
        ?OtpChallengeStore $challengeStore = null,
        ?OtpMailerPort $mailer = null,
        ?OtpGenerator $otpGenerator = null,
        ?ClockInterface $clock = null
    ) {
        $this->userRepository = $userRepository ?? new PdoUserRepository($database);
        $this->challengeStore = $challengeStore ?? new PhpSessionOtpChallengeStore();
        $this->mailer = $mailer ?? new PhpMailerOtpAdapter();
        $this->otpGenerator = $otpGenerator ?? new OtpGenerator();
        $this->clock = $clock ?? new SystemClock();
    }

    public function send(int $userId): void
    {
        $user = $this->salesEmployee($userId);
        $lastSentAt = $this->challengeStore->getLastSentAt();
        $waitSeconds = self::RESEND_WAIT_SECONDS - ($this->clock->now() - $lastSentAt);
        if ($waitSeconds > 0) {
            throw new DomainException(sprintf('Please wait %d seconds before requesting another code.', $waitSeconds));
        }

        $this->challengeStore->setLastSentAt($this->clock->now());
        $this->challengeStore->clearChallenge();
        $this->challengeStore->clearVerified();
        $code = $this->otpGenerator->generate(6);
        $this->mailer->sendUnlockOtp($user['email'], $user['name'], $code);

        $this->challengeStore->setChallenge([
            'userId' => $userId,
            'email' => $user['email'],
            'codeHash' => password_hash($code, PASSWORD_DEFAULT),
            'expiresAt' => $this->clock->now() + self::OTP_LIFETIME_SECONDS,
            'attempts' => 0,
        ]);
    }

    public function verify(int $userId, array $payload): void
    {
        $user = $this->salesEmployee($userId);
        $code = trim((string) ($payload['otp'] ?? ''));
        if (!preg_match('/^[0-9]{6}$/', $code)) {
            throw new \InvalidArgumentException('Enter the 6-digit email verification code.');
        }

        $challenge = $this->challengeStore->getChallenge();
        if (!is_array($challenge)
            || (int) ($challenge['userId'] ?? 0) !== $userId
            || !hash_equals((string) ($challenge['email'] ?? ''), $user['email'])
            || (int) ($challenge['expiresAt'] ?? 0) < $this->clock->now()) {
            $this->challengeStore->clearChallenge();
            throw new DomainException('The email verification code has expired. Request a new code.');
        }

        if ((int) $challenge['attempts'] >= self::MAX_ATTEMPTS) {
            $this->challengeStore->clearChallenge();
            throw new DomainException('Too many incorrect codes. Request a new code.');
        }

        if (!password_verify($code, (string) $challenge['codeHash'])) {
            $challenge['attempts'] = (int) $challenge['attempts'] + 1;
            $this->challengeStore->setChallenge($challenge);
            throw new DomainException('That code is incorrect. Check the email and try again.');
        }

        $this->challengeStore->clearChallenge();
        $this->challengeStore->setVerified([
            'userId' => $userId,
            'email' => $user['email'],
            'expiresAt' => $this->clock->now() + 1800,
        ]);
    }

    private function salesEmployee(int $userId): array
    {
        $user = $this->userRepository->findSalesEmployeeById($userId);
        if (!is_array($user) || !filter_var((string) ($user['email'] ?? ''), FILTER_VALIDATE_EMAIL)) {
            throw new DomainException('Your active employee email could not be verified. Contact your administrator.');
        }

        return [
            'name' => (string) $user['name'],
            'email' => (string) $user['email'],
        ];
    }

    private readonly UserRepository $userRepository;
    private readonly OtpChallengeStore $challengeStore;
    private readonly OtpMailerPort $mailer;
    private readonly OtpGenerator $otpGenerator;
    private readonly ClockInterface $clock;
}
