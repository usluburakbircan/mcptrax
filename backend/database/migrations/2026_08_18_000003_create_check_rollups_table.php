<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('check_rollups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('monitor_id')->constrained()->cascadeOnDelete();
            $table->timestamp('hour_start');
            $table->unsignedInteger('checks_count')->default(0);
            $table->unsignedInteger('failures_count')->default(0);
            $table->unsignedInteger('p50_ms')->nullable();
            $table->unsignedInteger('p95_ms')->nullable();
            $table->unsignedInteger('max_ms')->nullable();
            $table->timestamps();

            $table->unique(['monitor_id', 'hour_start']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('check_rollups');
    }
};
