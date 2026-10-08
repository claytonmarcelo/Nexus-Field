<?php

use Database\Seeders\DatabaseSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * A conta raiz trocou de endereço: `marcelolimadez@gmail.com` saiu do sistema por
 * medida de segurança e `nexusfield.admin@gmail.com` é a identidade absoluta.
 *
 * A troca não pode ser feita pelo model nem por request — a guarda do `User` proíbe
 * justamente mexer no e-mail de quem é raiz. Ela mora aqui, no schema, e renomeia a
 * linha em vez de criar outra: o `id` da conta raiz é referenciado por papéis,
 * auditoria e notificações, e identidade nova com histórico órfão seria outro bug.
 * A senha não passa perto daqui; ela continua só no `.env` e entra pelo seeder.
 */
return new class extends Migration
{
    public function up(): void
    {
        $aposentado = DatabaseSeeder::RETIRED_ROOT_EMAIL;
        $vigente = DatabaseSeeder::ROOT_EMAIL;

        if (DB::table('users')->where('email', $aposentado)->doesntExist()) {
            return;
        }

        if (DB::table('users')->where('email', $vigente)->exists()) {
            throw new RuntimeException(
                "Existe uma conta em {$vigente} e outra em {$aposentado}: a troca de identidade ".
                'precisa de uma linha só, então resolva o conflito antes de migrar.'
            );
        }

        DB::table('users')
            ->where('email', $aposentado)
            ->update(['email' => $vigente, 'updated_at' => now()]);
    }

    /**
     * Voltar atrás recolocaria no sistema o endereço que foi aposentado por medida
     * de segurança. Quem precisar desfazer escreve o SQL e responde por ele.
     */
    public function down(): void
    {
        throw new RuntimeException('Esta migration é irreversível: ela aposenta um endereço de raiz.');
    }
};
