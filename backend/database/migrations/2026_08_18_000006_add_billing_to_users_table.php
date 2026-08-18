<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('plan', 16)->default('free');
            $table->timestamp('pro_until')->nullable();
            $table->string('paddle_customer_id')->nullable()->index();
            $table->string('paddle_subscription_id')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['plan', 'pro_until', 'paddle_customer_id', 'paddle_subscription_id']);
        });
    }
};
