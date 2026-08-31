<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('taskflow_metrics', function (Blueprint $table) {
            $table->string('queue');
            $table->string('job_class');
            $table->integer('completed_count')->default(0);
            $table->integer('failed_count')->default(0);
            $table->double('total_latency')->default(0); // in milliseconds
            $table->primary(['queue', 'job_class']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('taskflow_metrics');
    }
};
