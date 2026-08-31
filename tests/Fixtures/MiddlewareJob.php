<?php

namespace Idoff\LaravelTaskflow\Tests\Fixtures;

class MiddlewareJob
{
    public static bool $wasExecuted = false;

    public function middleware(): array
    {
        return [SampleMiddleware::class];
    }

    public function handle(): void
    {
        self::$wasExecuted = true;
    }
}
