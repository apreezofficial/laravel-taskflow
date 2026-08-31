<?php

namespace Apreezofficial\LaravelTaskflow\Tests\Fixtures;

class SampleMiddleware
{
    public static bool $wasCalled = false;

    public function handle($job, $next)
    {
        self::$wasCalled = true;
        return $next($job);
    }
}
