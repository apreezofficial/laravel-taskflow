<?php

namespace Idoff\LaravelTaskflow\Tests\Fixtures;

class SampleJob
{
    public string $message;
    public static bool $wasExecuted = false;

    public function __construct(string $message)
    {
        $this->message = $message;
    }

    public function handle(): void
    {
        self::$wasExecuted = true;
    }
}
