<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('name');
            $table->string('trade_name')->nullable();
            $table->string('document', 25)->nullable();
            $table->string('email')->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('status', 32)->default('active')->index();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['company_id', 'document']);
            $table->index(['company_id', 'status']);
            $table->index(['company_id', 'name']);
        });

        Schema::create('client_contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('role', 80)->nullable();
            $table->boolean('is_primary')->default(false);
            $table->timestamps();

            $table->index('client_id');
        });

        Schema::create('addresses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->morphs('addressable');
            $table->string('type', 32)->default('service')->index();
            $table->string('zip_code', 10)->nullable();
            $table->string('street')->nullable();
            $table->string('number', 20)->nullable();
            $table->string('complement')->nullable();
            $table->string('neighborhood')->nullable();
            $table->string('city')->nullable();
            $table->string('state', 2)->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->boolean('is_primary')->default(false);
            $table->timestamps();

            $table->index(['company_id', 'addressable_type', 'addressable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('addresses');
        Schema::dropIfExists('client_contacts');
        Schema::dropIfExists('clients');
    }
};
