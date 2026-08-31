<?php

namespace Idoff\LaravelTaskflow\Tests\Unit;

use Idoff\LaravelTaskflow\Tests\TestCase;
use Idoff\LaravelTaskflow\Contracts\MetricsStore;
use Illuminate\Foundation\Testing\RefreshDatabase;

class DatabaseMetricsStoreTest extends TestCase
{
    use RefreshDatabase;

    protected MetricsStore $metricsStore;

    protected function setUp(): void
    {
        parent::setUp();
        $this->metricsStore = $this->app->make(MetricsStore::class);
    }

    public function test_it_can_increment_and_record_latency(): void
    {
        $this->metricsStore->increment('default', 'SampleJob', 'completed');
        $this->metricsStore->increment('default', 'SampleJob', 'completed');
        $this->metricsStore->increment('default', 'SampleJob', 'failed');
        
        $this->metricsStore->recordLatency('default', 'SampleJob', 150.5);
        $this->metricsStore->recordLatency('default', 'SampleJob', 250.0);

        $summary = $this->metricsStore->getSummary();

        $this->assertCount(1, $summary);
        $this->assertEquals('default', $summary[0]['queue']);
        $this->assertEquals('SampleJob', $summary[0]['job_class']);
        $this->assertEquals(2, $summary[0]['completed_count']);
        $this->assertEquals(1, $summary[0]['failed_count']);
        $this->assertEquals(400.5, $summary[0]['total_latency']);
    }
}
