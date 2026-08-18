<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('checks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('monitor_id')->constrained()->cascadeOnDelete();
            $table->timestamp('started_at')->index();
            $table->boolean('ok');
            $table->string('failed_phase', 32)->nullable(); // connect|initialize|tools_list|tool_call
            $table->string('error_class')->nullable();
            $table->text('error_message')->nullable();
            $table->unsignedInteger('latency_ms')->nullable();
            $table->unsignedInteger('connect_ms')->nullable();
            $table->unsignedInteger('tools_list_ms')->nullable();
            $table->unsignedInteger('tool_call_ms')->nullable();
            $table->string('server_name')->nullable();
            $table->string('server_version', 64)->nullable();
            $table->string('protocol_version', 32)->nullable();
            $table->unsignedInteger('tools_count')->nullable();
            $table->boolean('tools_drift')->default(false);
            $table->timestamp('created_at')->nullable();

            $table->index(['monitor_id', 'started_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('checks');
    }
};
