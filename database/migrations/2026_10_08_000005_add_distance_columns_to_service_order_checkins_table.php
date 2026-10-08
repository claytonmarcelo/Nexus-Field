<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A distância, em metros, entre a posição lida no aparelho e o endereço da
     * ordem, calculada pelo servidor no instante do carimbo.
     *
     * Ela é gravada e não recalculada na leitura por dois motivos. O primeiro é o
     * preço: recalcular supõe que as coordenadas da ordem continuam as mesmas, e
     * um endereço corrigido depois da visita reescreveria o passado de quem já
     * esteve lá. O segundo é o raio: a empresa pode ajustar a tolerância amanhã, e
     * a visita de hoje tem de responder pelo raio que valia quando aconteceu.
     */
    public function up(): void
    {
        Schema::table('service_order_checkins', function (Blueprint $table) {
            $table->decimal('checkin_distance', 8, 2)->nullable()->after('checkin_longitude');
            $table->decimal('checkout_distance', 8, 2)->nullable()->after('checkout_longitude');
        });
    }

    public function down(): void
    {
        Schema::table('service_order_checkins', function (Blueprint $table) {
            $table->dropColumn(['checkin_distance', 'checkout_distance']);
        });
    }
};
