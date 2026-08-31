<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('taskflow_schedules', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('job_class');
            $table->json('payload');
            $table->string('cron_expression');
            $table->string('queue')->default('default');
            $table->timestamp('last_dispatched_at')->nullable();
            $table->timestamp('next_run_at')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('taskflow_schedules');
    }
};
