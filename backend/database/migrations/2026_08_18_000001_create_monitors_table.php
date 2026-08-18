<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('monitors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('url', 2048);
            $table->string('transport', 32)->default('streamable_http');
            $table->string('auth_header_name', 128)->nullable();
            $table->text('auth_header_value')->nullable(); // encrypted cast
            $table->unsignedInteger('interval_seconds')->default(900);
            $table->string('synthetic_tool_name')->nullable();
            $table->json('synthetic_tool_args')->nullable();
            $table->string('synthetic_expect_substring', 512)->nullable();
            $table->string('tools_hash', 64)->nullable();
            $table->json('tool_names')->nullable();
            $table->string('status', 16)->default('pending'); // pending|up|down|degraded
            $table->unsignedInteger('consecutive_failures')->default(0);
            $table->unsignedInteger('failure_threshold')->default(2);
            $table->timestamp('next_check_at')->nullable()->index();
            $table->timestamp('last_checked_at')->nullable();
            $table->timestamp('last_status_change_at')->nullable();
            $table->boolean('is_public')->default(false);
            $table->string('slug', 64)->nullable()->unique();
            $table->timestamp('paused_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('monitors');
    }
};
