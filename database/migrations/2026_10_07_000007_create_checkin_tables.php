<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_order_checkins', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('service_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('technician_id')->constrained()->restrictOnDelete();
            $table->timestamp('checkin_at');
            $table->decimal('checkin_latitude', 10, 7)->nullable();
            $table->decimal('checkin_longitude', 10, 7)->nullable();
            $table->timestamp('checkout_at')->nullable();
            $table->decimal('checkout_latitude', 10, 7)->nullable();
            $table->decimal('checkout_longitude', 10, 7)->nullable();
            $table->string('status', 24)->default('open')->index();
            $table->text('observation')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'technician_id', 'checkin_at']);
            $table->index(['service_order_id', 'checkout_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_order_checkins');
    }
};
