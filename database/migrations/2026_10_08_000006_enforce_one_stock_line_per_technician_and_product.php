<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Um técnico carrega um produto uma única vez. É sobre essa linha que o registro
     * de movimentação toma a trava (`TechnicianStock::travar`), e sem o índice duas
     * cargas vazias lidas ao mesmo tempo criariam duas linhas para o mesmo par — o
     * saldo do técnico deixaria de ser um número só.
     */
    public function up(): void
    {
        Schema::table('technician_stocks', function (Blueprint $table) {
            $table->unique(
                ['technician_id', 'product_id'],
                'technician_stocks_tecnico_produto_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::table('technician_stocks', function (Blueprint $table) {
            $table->dropUnique('technician_stocks_tecnico_produto_unique');
        });
    }
};
