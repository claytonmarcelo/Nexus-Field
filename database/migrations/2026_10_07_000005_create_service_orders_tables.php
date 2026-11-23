<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('client_id')->constrained()->restrictOnDelete();
            $table->foreignId('service_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('technician_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('team_id')->nullable()->constrained('teams')->nullOnDelete();
            $table->string('number');
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('priority', 16)->default('normal')->index();
            $table->string('status', 32)->default('open')->index();
            $table->timestamp('scheduled_starts_at')->nullable();
            $table->timestamp('scheduled_ends_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('execution_notes')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->decimal('discount', 14, 2)->default(0);
            $table->string('street')->nullable();
            $table->string('number_address', 20)->nullable();
            $table->string('complement')->nullable();
            $table->string('neighborhood')->nullable()->index();
            $table->string('city')->nullable();
            $table->string('state', 2)->nullable();
            $table->string('zip_code', 10)->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'number']);
            $table->index(['company_id', 'status', 'scheduled_starts_at']);
            $table->index(['company_id', 'client_id']);
            $table->index(['company_id', 'technician_id']);
            $table->index(['company_id', 'priority']);
        });

        Schema::create('service_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_order_id')->constrained()->cascadeOnDelete();
            // Item de OS é registro histórico: restringir a exclusão (o catálogo usa
            // soft delete) também libera o uso das colunas no CHECK abaixo.
            $table->foreignId('service_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('description');
            $table->decimal('quantity', 14, 4)->default(1);
            $table->decimal('unit_price', 14, 2)->default(0);
            $table->decimal('discount', 14, 2)->default(0);
        });

        // Um item é ou serviço ou produto consumido; nunca ambos, nunca nenhum.
        DB::statement('ALTER TABLE `service_order_items` ADD CONSTRAINT `item_is_service_or_product` CHECK ((`service_id` is not null) <> (`product_id` is not null))');

        Schema::create('service_order_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('technician_id')->constrained()->cascadeOnDelete();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('assigned_at');
            $table->timestamp('released_at')->nullable();
            $table->text('note')->nullable();

            $table->unique(['service_order_id', 'technician_id']);
        });

        Schema::create('service_order_status_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('from_status', 32)->nullable();
            $table->string('to_status', 32);
            $table->text('note')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['service_order_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_order_status_history');
        Schema::dropIfExists('service_order_assignments');
        Schema::dropIfExists('service_order_items');
        Schema::dropIfExists('service_orders');
    }
};
