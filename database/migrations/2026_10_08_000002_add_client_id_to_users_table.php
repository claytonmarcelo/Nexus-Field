<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A conta de cliente precisava de uma ponte com o cadastro de cliente: sem ela,
 * o papel "Cliente" que tem `orders.view` enxergaria a operação inteira da
 * empresa. A ponte é uma coluna em users, não uma tabela à parte — um usuário atende
 * uma carteira, do mesmo jeito que a ficha de campo é um técnico por usuário.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('client_id')->nullable()
                ->constrained('clients')->nullOnDelete();
            $table->index(['company_id', 'client_id']);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['company_id', 'client_id']);
            $table->dropConstrainedForeignId('client_id');
        });
    }
};
