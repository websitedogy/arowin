<?php

declare(strict_types=1);

use Arowin\Shared\AppTime;
use PHPUnit\Framework\TestCase;

final class AppTimeTest extends TestCase
{
    public function testNowUsesAsiaKolkataTimezone(): void
    {
        $now = AppTime::now();

        $this->assertSame('Asia/Kolkata', $now->getTimezone()->getName());
        $this->assertSame('Asia/Kolkata', AppTime::timeZone()->getName());
    }
}
