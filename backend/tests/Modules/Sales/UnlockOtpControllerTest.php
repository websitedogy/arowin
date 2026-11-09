<?php

declare(strict_types=1);

use Arowin\ClockInterface;
use Arowin\OtpGenerator;
use Arowin\Modules\Sales\OtpChallengeStore;
use Arowin\Modules\Sales\OtpMailerPort;
use Arowin\Modules\Sales\UnlockOtpController;
use Arowin\Modules\Sales\UserRepository;
use PHPUnit\Framework\TestCase;
use PDO;

final class UnlockOtpControllerTest extends TestCase
{
    public function testSendAndVerifyOtpFlowUsesInjectedDependencies(): void
    {
        $userRepository = new class implements UserRepository {
            public function findSalesEmployeeById(int $userId): ?array
            {
                if ($userId !== 42) {
                    return null;
                }
                return ['name' => 'Alice Telecaller', 'email' => 'alice@example.com'];
            }
        };
        $challengeStore = new class implements OtpChallengeStore {
            public int $lastSentAt = 0;
            public ?array $challenge = null;
            public ?array $verified = null;

            public function getLastSentAt(): int
            {
                return $this->lastSentAt;
            }

            public function setLastSentAt(int $timestamp): void
            {
                $this->lastSentAt = $timestamp;
            }

            public function getChallenge(): ?array
            {
                return $this->challenge;
            }

            public function setChallenge(array $challenge): void
            {
                $this->challenge = $challenge;
            }

            public function clearChallenge(): void
            {
                $this->challenge = null;
            }

            public function getVerified(): ?array
            {
                return $this->verified;
            }

            public function setVerified(array $verified): void
            {
                $this->verified = $verified;
            }

            public function clearVerified(): void
            {
                $this->verified = null;
            }
        };
        $mailer = new class implements OtpMailerPort {
            public ?array $lastMail = null;

            public function sendUnlockOtp(string $email, string $name, string $code): void
            {
                $this->lastMail = compact('email', 'name', 'code');
            }
        };
        $clock = new class implements ClockInterface {
            public int $now;

            public function __construct()
            {
                $this->now = 1700000000;
            }

            public function now(): int
            {
                return $this->now;
            }
        };
        $otpGenerator = new class extends OtpGenerator {
            public function generate(int $digits = 6): string
            {
                return '123456';
            }
        };

        $controller = new UnlockOtpController(new PDO('sqlite::memory:'), $userRepository, $challengeStore, $mailer, $otpGenerator, $clock);

        $controller->send(42);
        $this->assertSame('alice@example.com', $mailer->lastMail['email']);
        $this->assertSame('123456', $mailer->lastMail['code']);
        $this->assertTrue(password_verify('123456', (string) $challengeStore->challenge['codeHash']));

        $controller->verify(42, ['otp' => '123456']);
        $this->assertSame('alice@example.com', $challengeStore->verified['email']);
        $this->assertSame(42, $challengeStore->verified['userId']);
        $this->assertNull($challengeStore->challenge);
    }

    public function testVerifyRejectsExpiredAndIncorrectOtps(): void
    {
        $userRepository = new class implements UserRepository {
            public function findSalesEmployeeById(int $userId): ?array
            {
                return ['name' => 'Alice Telecaller', 'email' => 'alice@example.com'];
            }
        };
        $challengeStore = new class implements OtpChallengeStore {
            public int $lastSentAt = 0;
            public ?array $challenge = null;
            public ?array $verified = null;

            public function getLastSentAt(): int { return $this->lastSentAt; }
            public function setLastSentAt(int $timestamp): void { $this->lastSentAt = $timestamp; }
            public function getChallenge(): ?array { return $this->challenge; }
            public function setChallenge(array $challenge): void { $this->challenge = $challenge; }
            public function clearChallenge(): void { $this->challenge = null; }
            public function getVerified(): ?array { return $this->verified; }
            public function setVerified(array $verified): void { $this->verified = $verified; }
            public function clearVerified(): void { $this->verified = null; }
        };
        $mailer = new class implements OtpMailerPort {
            public function sendUnlockOtp(string $email, string $name, string $code): void
            {
            }
        };
        $clock = new class implements ClockInterface {
            public int $now;
            public function __construct(int $now = 1700000000) { $this->now = $now; }
            public function now(): int { return $this->now; }
        };
        $otpGenerator = new class extends OtpGenerator {
            public function generate(int $digits = 6): string
            {
                return '123456';
            }
        };

        $controller = new UnlockOtpController(new PDO('sqlite::memory:'), $userRepository, $challengeStore, $mailer, $otpGenerator, $clock);
        $controller->send(9);

        $this->expectException(\InvalidArgumentException::class);
        $controller->verify(9, ['otp' => 'abc']);
    }
}
