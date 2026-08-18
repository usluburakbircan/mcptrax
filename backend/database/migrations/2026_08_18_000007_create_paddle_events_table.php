<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('paddle_events', function (Blueprint $table) {
            $table->id();
            // Tekrar elemenin kilidi bu UNIQUE kısıt: aynı olayın iki kopyası
            // aynı anda gelse bile ikincisi insert'te düşer.
            $table->string('event_id')->unique();
            $table->string('event_type');
            $table->timestamp('occurred_at')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->string('outcome', 191)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('paddle_events');
    }
};
