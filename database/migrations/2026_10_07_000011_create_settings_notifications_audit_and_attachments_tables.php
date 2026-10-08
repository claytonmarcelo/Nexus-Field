<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('key', 96);
            $table->json('value')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'key']);
        });

        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('type', 48)->index();
            $table->string('title');
            $table->text('body')->nullable();
            $table->string('link')->nullable();
            $table->json('data')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'user_id', 'read_at']);
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->nullable()->constrained('companies')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('user_name')->nullable();
            $table->string('action', 64)->index();
            $table->string('entity_type', 128)->nullable();
            $table->string('entity_id', 64)->nullable();
            $table->string('description')->nullable();
            $table->json('changes')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamp('created_at')->nullable()->index();

            $table->index(['company_id', 'entity_type', 'entity_id']);
            $table->index(['company_id', 'created_at']);
        });

        // Substitui as colunas soltas do legado (beforePhoto, before_photo_url,
        // signatureBase64 duplicadas) por um único registro por arquivo.
        Schema::create('attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->morphs('attachable');
            $table->string('collection', 48)->default('generic')->index();
            $table->string('disk', 32)->default('public');
            $table->string('path');
            $table->string('original_name');
            $table->string('mime_type', 128)->nullable();
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'attachable_type', 'attachable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attachments');
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('notifications');
        Schema::dropIfExists('company_settings');
    }
};
