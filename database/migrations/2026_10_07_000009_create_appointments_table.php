<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('appointments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('technician_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('client_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('service_order_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('ticket_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('type', 32)->default('custom')->index();
            $table->string('status', 32)->default('scheduled')->index();
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->boolean('all_day')->default(false);
            $table->string('location')->nullable();
            $table->timestamps();

            // Agenda é lida por janela de tempo, quase sempre dentro do tenant.
            $table->index(['company_id', 'starts_at', 'ends_at']);
            $table->index(['company_id', 'technician_id', 'starts_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appointments');
    }
};
