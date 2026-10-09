<?php

namespace App\Support;

/**
 * O menu de escolhas da empresa, declarado uma única vez. Cada chave aqui é um
 * número ou um ligamento que alguma rotina do sistema já consulta: o raio mora
 * no aceito do check-in, a janela mora na varredura que avisa o vencimento, e os
 * três ligamentos são as três famílias que a varredura de todo dia toca. Sem
 * chave no catálogo não tem campo em tela — e sem consumo no código não tem
 * chave no catálogo. Configuração que nada lê é enfeite, e enfeite é mentira.
 */
final class SettingsCatalog
{
    public const RAIO_CHECKIN = 'checkin_raio';

    public const DIAS_ALERTA_VENCIMENTO = 'financeiro_dias_alerta';

    public const AVISO_ORDENS_ATRASADAS = 'varredura_ordens_atrasadas';

    public const AVISO_VENCIMENTOS = 'varredura_vencimentos';

    public const AVISO_AGENDA = 'varredura_agenda';

    /**
     * @var array<string, array{rotulo: string, ajuda: string, padrao: mixed, regras: array<int, string>}>
     */
    public const PREFERENCIAS = [
        self::RAIO_CHECKIN => [
            'rotulo' => 'Raio aceito no check-in (metros)',
            'ajuda' => 'A distância máxima entre a posição lida no celular e o endereço da ordem. '
                .'Fora deste raio a chegada fica marcada como suspeita. Vazio usa o padrão da casa: 250 m.',
            'padrao' => 250,
            'regras' => ['nullable', 'integer', 'between:50,5000'],
        ],
        self::DIAS_ALERTA_VENCIMENTO => [
            'rotulo' => 'Janela do alerta de vencimento (dias)',
            'ajuda' => 'Quantos dias antes do vencimento a varredura diária avisa quem lê o financeiro. '
                .'Vazio usa o padrão da casa: 2 dias.',
            'padrao' => 2,
            'regras' => ['nullable', 'integer', 'between:1,30'],
        ],
        self::AVISO_ORDENS_ATRASADAS => [
            'rotulo' => 'Ordens atrasadas',
            'ajuda' => 'A varredura de todo dia toca responsável, comissão e quem aprova escala quando o '
                .'fim previsto passou e a ordem segue aberta.',
            'padrao' => true,
            'regras' => ['boolean'],
        ],
        self::AVISO_VENCIMENTOS => [
            'rotulo' => 'Vencimentos de receita e despesa',
            'ajuda' => 'A conta que bate na janela escolhida acima acorda quem tem acesso ao financeiro.',
            'padrao' => true,
            'regras' => ['boolean'],
        ],
        self::AVISO_AGENDA => [
            'rotulo' => 'Agenda de amanhã',
            'ajuda' => 'O compromisso marcado para o dia seguinte toca o técnico da janela e a carteira do cliente.',
            'padrao' => true,
            'regras' => ['boolean'],
        ],
    ];

    /** @return array<string, string> */
    public static function chaves(): array
    {
        return array_column(self::PREFERENCIAS, 'rotulo');
    }
}
