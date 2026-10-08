<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->timestamps();

            $table->unique(['company_id', 'slug']);
        });

        Schema::create('services', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('service_category_id')->nullable()->constrained()->nullOnDelete();
            $table->string('code')->nullable();
            $table->string('name');
            $table->text('description')->nullable();
            $table->decimal('price', 14, 2)->default(0);
            $table->unsignedInteger('estimated_minutes')->nullable();
            $table->string('status', 32)->default('active')->index();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['company_id', 'name']);
            $table->index(['company_id', 'status']);
        });

        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('sku');
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('unit', 10)->default('un');
            $table->decimal('cost', 14, 2)->default(0);
            $table->decimal('price', 14, 2)->default(0);
            $table->decimal('reorder_point', 14, 4)->default(0);
            $table->string('status', 32)->default('active')->index();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['company_id', 'sku']);
            $table->index(['company_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
        Schema::dropIfExists('services');
        Schema::dropIfExists('service_categories');
    }
};
