<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('specialties', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->text('description')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'slug']);
        });

        Schema::create('technicians', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('document', 25)->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('email')->nullable();
            $table->string('status', 32)->default('available')->index();
            $table->string('region')->nullable()->index();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->timestamp('last_location_at')->nullable();
            $table->date('admission_date')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['company_id', 'user_id']);
            $table->index(['company_id', 'status']);
        });

        Schema::create('specialty_technician', function (Blueprint $table) {
            $table->foreignId('specialty_id')->constrained()->cascadeOnDelete();
            $table->foreignId('technician_id')->constrained()->cascadeOnDelete();

            $table->primary(['specialty_id', 'technician_id']);
        });

        Schema::create('teams', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('name');
            $table->foreignId('leader_id')->nullable()->constrained('technicians')->nullOnDelete();
            $table->string('region')->nullable();
            $table->string('status', 32)->default('active')->index();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['company_id', 'name']);
        });

        Schema::create('team_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('technician_id')->constrained()->cascadeOnDelete();
            $table->timestamp('joined_at')->nullable();
            $table->timestamp('left_at')->nullable();

            $table->unique(['team_id', 'technician_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('team_members');
        Schema::dropIfExists('teams');
        Schema::dropIfExists('specialty_technician');
        Schema::dropIfExists('technicians');
        Schema::dropIfExists('specialties');
    }
};
