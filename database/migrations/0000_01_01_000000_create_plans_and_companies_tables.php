<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->unsignedInteger('max_users')->default(5);
            $table->unsignedInteger('max_technicians')->default(5);
            $table->unsignedInteger('max_clients')->default(100);
            $table->unsignedInteger('max_service_orders_per_month')->default(500);
            $table->decimal('price_monthly', 14, 2)->default(0);
            $table->json('features')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('document', 25)->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('email')->nullable();
            $table->string('website')->nullable();
            $table->string('logo_path')->nullable();
            $table->foreignId('plan_id')->constrained('plans')->restrictOnDelete();
            $table->string('subscription_status', 32)->default('trial')->index();
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('subscribed_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->string('status', 32)->default('active')->index();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('companies');
        Schema::dropIfExists('plans');
    }
};
