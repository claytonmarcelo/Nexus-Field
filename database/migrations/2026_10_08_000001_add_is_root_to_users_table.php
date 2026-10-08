<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A conta raiz é uma propriedade do schema, não uma string comparada em cada
     * controller: marcado no banco, a proteção sobrevive a troca de e-mail no
     * ambiente, a um `sync` de papéis e a quem herdar a operação.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_root')->default(false)->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('is_root');
        });
    }
};
