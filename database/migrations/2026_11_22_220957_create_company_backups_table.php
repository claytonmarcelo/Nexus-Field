<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Cada empresa guarda seus próprios arquivos de back‑up. A tabela só
     * registra metadados: o conteúdo fica no disco privado, fora da web, e a
     * chave estrangeira faz o registro desaparecer junto com a empresa.
     */
    public function up(): void
    {
        Schema::create('company_backups', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('tipo', 32)->default('configuracao');
            $table->string('disk', 32)->default('local');
            $table->string('path');
            $table->string('nome_arquivo');
            $table->string('checksum', 64);
            $table->unsignedBigInteger('size_bytes');
            $table->unsignedInteger('total_configuracoes');
            $table->json('resumo')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('restaurado_em')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_backups');
    }
};
