# Laravel TaskFlow

[![CI](https://github.com/idoff/laravel-taskflow/actions/workflows/ci.yml/badge.svg)](https://github.com/idoff/laravel-taskflow/actions)
[![License: MIT](https://img.shields.io/badge/License-MIT-blue.svg)](https://opensource.org/licenses/MIT)

**Laravel TaskFlow** is a production-quality, high-performance asynchronous job processing engine for Laravel. It provides highly reliable database and Redis-backed task runners, custom concurrency locking, automatic backoff retries, rate limiting, and administrative CLI utilities.

## Features

- **Multiple Drivers**: Swappable storage engines for SQL databases (MySQL/PostgreSQL) and Redis.
- **Concurrent Execution Safety**: Uses row locks (`lockForUpdate` & `skipLocked`) on relational databases to prevent double-processing.
- **Automatic Retries**: Support for exponential backoff delays on failed attempts.
- **Job Timeouts**: Interrupts long-running jobs exceeding defined thresholds via Unix signal alarms (`pcntl_alarm`).
- **Rate Limiting**: Integrated queue-level throttling utilizing Laravel's `RateLimiter`.
- **Administrative CLI**: Built-in Artisan commands to monitor, retry, and cancel jobs.

---

## Installation

Add the library to your Laravel project via Composer:

```bash
composer require idoff/laravel-taskflow
```

### Migration Setup (for Database driver)

If you are using the database storage driver, publish and run the jobs table migrations:

```bash
php artisan migrate
```

---

## Configuration

Publish the configuration file to customize the default store and queue names:

```bash
php artisan vendor:publish --tag=taskflow-config
```

### Options (`config/taskflow.php`)

```php
return [
    // Driver options: "database" or "redis"
    'driver' => env('TASKFLOW_DRIVER', 'database'),

    // Default queue to process
    'default_queue' => 'default',
];
```

---

## Usage

### 1. Creating a Job

TaskFlow can process standard PHP classes containing a `handle` method:

```php
namespace App\Jobs;

class SendWelcomeEmail
{
    public string $email;
    public int $tries = 3;      // Optional: Max retry attempts
    public int $timeout = 30;   // Optional: Timeout in seconds

    public function __construct(string $email)
    {
        $this->email = $email;
    }

    public function handle(): void
    {
        // Processing logic
    }
}
```

### 2. Dispatching a Job

Use the `TaskFlow` dispatcher to publish tasks to the queues:

```php
use App\Jobs\SendWelcomeEmail;
use Idoff\LaravelTaskflow\TaskFlow;

// Dispatch using helper/facade
app(TaskFlow::class)->dispatch(new SendWelcomeEmail('user@example.com'));

// Dispatch with delay and custom priority
app(TaskFlow::class)->dispatch(
    job: new SendWelcomeEmail('user@example.com'),
    queue: 'emails',
    priority: 10,
    delay: 60 // Delay in seconds
);
```

### 3. Scheduling a Recurring Job

Register cron-based schedules that are evaluated and queued automatically:

```php
// Register a recurring task using cron-syntax
app(TaskFlow::class)->schedule(
    jobClass: SendWelcomeEmail::class,
    data: ['email' => 'admin@example.com'],
    cronExpression: '*/5 * * * *', // Run every 5 minutes
    queue: 'emails'
);
```

To evaluate and dispatch due recurring tasks, run the schedule run daemon:

```bash
php artisan taskflow:schedule-run
```

---

## Processing Queues (Artisan Worker)

Run the console worker command to begin pulling and executing queued jobs:

```bash
# Process jobs on the default queue
php artisan taskflow:work

# Process jobs on a custom queue
php artisan taskflow:work --queue=emails

# Throttled queue worker (process maximum of 10 jobs per minute)
php artisan taskflow:work --rate-limit=10
```

---

## Job Management CLI

Manage, debug, and recover queued jobs with the administrative command set:

### List Failed Jobs
```bash
php artisan taskflow:failed
```

### Retry a Failed Job
```bash
php artisan taskflow:retry {job_id}
```

### Cancel a Queued Job
```bash
php artisan taskflow:cancel {job_id}
```

---

## Testing

Run tests locally:

```bash
vendor/bin/phpunit
```

Or via helper scripts:

```bash
# Windows
run-tests.bat

# Unix
./run-tests.sh
```

---

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
