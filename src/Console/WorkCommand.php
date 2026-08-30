<?php

namespace Idoff\LaravelTaskflow\Console;

use Illuminate\Console\Command;
use Idoff\LaravelTaskflow\TaskFlow;
use Illuminate\Container\Container;
use Illuminate\Support\Facades\RateLimiter;
use Throwable;

class WorkCommand extends Command
{
    protected $signature = 'taskflow:work
                            {--queue=default : The queue to watch}
                            {--sleep=3 : Seconds to sleep when no jobs are available}
                            {--once : Only process the first available job and exit}
                            {--rate-limit=0 : Max jobs to process per minute (0 for unlimited)}';

    protected $description = 'Start processing jobs on the TaskFlow queue';

    protected bool $shouldQuit = false;

    public function handle(TaskFlow $taskflow): int
    {
        $this->info("TaskFlow worker starting for queue: {$this->option('queue')}");

        $this->listenForSignals();

        $queue = $this->option('queue');
        $rateLimit = (int) $this->option('rate-limit');

        while (true) {
            if ($this->shouldQuit) {
                $this->info("Worker shutting down gracefully.");
                break;
            }

            // Check rate limiter
            if ($rateLimit > 0) {
                $key = "taskflow:limiter:{$queue}";
                if (RateLimiter::tooManyAttempts($key, $rateLimit)) {
                    $seconds = RateLimiter::availableIn($key);
                    $this->info("Rate limit exceeded for queue {$queue}. Sleeping for {$seconds} seconds.");
                    sleep(max(1, $seconds));
                    continue;
                }
                RateLimiter::hit($key, 60);
            }

            try {
                $job = $taskflow->getStore()->pop($queue);

                if ($job) {
                    $this->processJob($taskflow, $job);
                } else {
                    if ($this->option('once')) {
                        break;
                    }
                    sleep((int) $this->option('sleep'));
                }
            } catch (Throwable $e) {
                $this->error("Worker loop encountered error: " . $e->getMessage());
                sleep((int) $this->option('sleep'));
            }

            if ($this->option('once')) {
                break;
            }
        }

        return 0;
    }

    protected function processJob(TaskFlow $taskflow, array $jobData): void
    {
        $id = $jobData['id'];
        $payload = $jobData['payload'];
        $jobClass = $payload['job'];
        $data = $payload['data'];
        $attempts = (int) $jobData['attempts'];
        $maxAttempts = (int) $jobData['max_attempts'];
        $timeout = (int) ($jobData['timeout'] ?? 60);

        $this->info("[$id] Processing: $jobClass (Attempt $attempts/$maxAttempts)");

        try {
            $this->registerTimeoutAlarm($timeout);

            // Instantiate the job via container (injecting constructor parameters if matching keys exist, or using empty constructor)
            $jobInstance = $this->resolveJob($jobClass, $data);

            // Execute handle/invoke
            if (method_exists($jobInstance, 'handle')) {
                Container::getInstance()->call([$jobInstance, 'handle']);
            } elseif (is_callable($jobInstance)) {
                $jobInstance();
            } else {
                throw new \RuntimeException("Job class does not define a handle method or __invoke.");
            }

            $this->disableTimeoutAlarm();

            $taskflow->getStore()->complete($id);
            $this->info("[$id] Processed: $jobClass");
        } catch (Throwable $e) {
            $this->disableTimeoutAlarm();
            $this->error("[$id] Failed: $jobClass - " . $e->getMessage());

            if ($attempts < $maxAttempts) {
                // Exponential backoff
                $delay = (int) pow(2, $attempts);
                $taskflow->getStore()->release($id, $delay);
                $this->info("[$id] Released back to queue with delay of $delay seconds.");
            } else {
                $taskflow->getStore()->fail($id, $e->getTraceAsString());
            }
        }
    }

    protected function resolveJob(string $jobClass, array $data): object
    {
        if (!class_exists($jobClass)) {
            throw new \RuntimeException("Job class $jobClass does not exist.");
        }

        // Try to construct with arguments matching data keys
        $reflector = new \ReflectionClass($jobClass);
        $constructor = $reflector->getConstructor();

        if (null === $constructor) {
            return new $jobClass();
        }

        $parameters = $constructor->getParameters();
        $args = [];

        foreach ($parameters as $parameter) {
            $name = $parameter->getName();
            if (array_key_exists($name, $data)) {
                $args[] = $data[$name];
            } elseif ($parameter->isDefaultValueAvailable()) {
                $args[] = $parameter->getDefaultValue();
            } else {
                // Try resolving via container
                $args[] = Container::getInstance()->make($parameter->getType()?->getName() ?? $name);
            }
        }

        return $reflector->newInstanceArgs($args);
    }

    protected function listenForSignals(): void
    {
        if (extension_loaded('pcntl')) {
            pcntl_async_signals(true);

            pcntl_signal(SIGTERM, function () {
                $this->shouldQuit = true;
            });

            pcntl_signal(SIGINT, function () {
                $this->shouldQuit = true;
            });
        }
    }

    protected function registerTimeoutAlarm(int $timeout): void
    {
        if (extension_loaded('pcntl')) {
            pcntl_async_signals(true);

            pcntl_signal(SIGALRM, function () {
                throw new \RuntimeException("Job execution exceeded timeout limit.");
            });

            pcntl_alarm($timeout);
        }
    }

    protected function disableTimeoutAlarm(): void
    {
        if (extension_loaded('pcntl')) {
            pcntl_alarm(0);
        }
    }
}
