<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Passagem de estado do chamado. A modelagem da fase 2 deu fluxo de estado às
 * ordens e deixou os chamados só com os carimbos (`opened_at`, `resolved_at`,
 * `closed_at`). Com tela de chamado no ar, falta responder na ficha quem moveu o
 * chamado e por quê — o que o README promete para ordem e chamado. A mesa é a
 * mesma de `service_order_status_history`: linha por passagem, com autor e nota.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ticket_status_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('from_status', 32)->nullable();
            $table->string('to_status', 32);
            $table->text('note')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['ticket_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_status_history');
    }
};
