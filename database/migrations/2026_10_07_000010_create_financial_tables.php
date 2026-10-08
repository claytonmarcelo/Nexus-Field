<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Valores em DECIMAL(14,2): o legado usava double, que gera erro de
        // arredondamento em soma de financeiro.
        Schema::create('financial_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('client_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('service_order_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 16);
            $table->string('category', 64)->index();
            $table->string('description');
            $table->decimal('amount', 14, 2);
            $table->date('due_date')->index();
            $table->date('occurred_at')->nullable();
            $table->string('status', 24)->default('pending')->index();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'type', 'status']);
            $table->index(['company_id', 'due_date']);
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('financial_record_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('amount', 14, 2);
            $table->string('method', 32);
            $table->string('reference')->nullable();
            $table->date('paid_at');
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'paid_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
        Schema::dropIfExists('financial_records');
    }
};
