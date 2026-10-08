<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A quantidade em estoque é derivada das movimentações (fonte de verdade),
        // por isso products não guarda coluna de saldo.
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('technician_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('service_order_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 24);
            $table->decimal('quantity', 14, 4);
            $table->decimal('unit_cost', 14, 2)->nullable();
            $table->text('note')->nullable();
            $table->timestamp('recorded_at');
            $table->timestamps();

            $table->index(['company_id', 'product_id', 'recorded_at']);
            $table->index(['company_id', 'type']);
            $table->index('service_order_id');
        });

        // Estoque físico carregado pelo técnico: estado por par técnico/produto,
        // reconciliado pelas movimentações do tipo load/consume/return.
        Schema::create('technician_stocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('technician_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->decimal('quantity', 14, 4)->default(0);
            $table->timestamps();

            $table->unique(['technician_id', 'product_id']);
            $table->index('company_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('technician_stocks');
        Schema::dropIfExists('stock_movements');
    }
};
