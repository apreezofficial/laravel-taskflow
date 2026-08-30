<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('taskflow_jobs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('queue')->index();
            $table->json('payload');
            $table->string('status')->default('pending')->index();
            $table->integer('priority')->default(0)->index();
            $table->integer('attempts')->default(0);
            $table->integer('max_attempts')->default(3);
            $table->integer('timeout')->default(60);
            $table->timestamp('available_at')->nullable()->index();
            $table->timestamp('reserved_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->longText('exception')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('taskflow_jobs');
    }
};
