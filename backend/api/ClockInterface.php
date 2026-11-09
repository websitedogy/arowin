<?php

declare(strict_types=1);

namespace Arowin;

interface ClockInterface
{
    public function now(): int;
}
