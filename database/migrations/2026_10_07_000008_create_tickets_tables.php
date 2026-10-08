<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tickets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('client_id')->constrained()->restrictOnDelete();
            $table->foreignId('service_order_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('responsible_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('technician_id')->nullable()->constrained()->nullOnDelete();
            $table->string('protocol');
            $table->string('subject');
            $table->text('description')->nullable();
            $table->string('category', 64)->index();
            $table->string('priority', 16)->default('normal')->index();
            $table->string('status', 32)->default('open')->index();
            $table->timestamp('opened_at');
            $table->timestamp('resolved_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->text('resolution_note')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'protocol']);
            $table->index(['company_id', 'status', 'priority']);
            $table->index(['company_id', 'client_id']);
        });

        Schema::create('ticket_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('client_id')->nullable()->constrained()->nullOnDelete();
            $table->text('body');
            $table->boolean('is_internal')->default(false);
            $table->timestamps();

            $table->index(['ticket_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_comments');
        Schema::dropIfExists('tickets');
    }
};
