<?php

namespace App\Console\Commands;

use App\Services\BackfillOrganizacional;
use Illuminate\Console\Command;

class BackfillOrganizacao extends Command
{
    protected $signature = 'nf:organizacao:backfill';

    protected $description = 'Cria a estrutura organizacional padrao das empresas existentes, de forma idempotente';

    public function handle(BackfillOrganizacional $servico): int
    {
        $totais = $servico->executar();

        $this->info(sprintf(
            '%d empresa(s): %d filial(is), %d setor(es), %d cargo(s), %d vinculo(s), %d alcance(s).',
            $totais['empresas'],
            $totais['filiais'],
            $totais['setores'],
            $totais['cargos'],
            $totais['vinculos'],
            $totais['alcances'],
        ));

        return self::SUCCESS;
    }
}
