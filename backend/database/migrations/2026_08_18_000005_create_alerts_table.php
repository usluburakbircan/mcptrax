<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('monitor_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 16)->default('down'); // down|drift
            $table->timestamp('opened_at');
            $table->timestamp('resolved_at')->nullable();
            $table->string('reason', 64)->nullable(); // failed phase at open time
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index(['monitor_id', 'resolved_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alerts');
    }
};
