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

    public const BACKUP_RETENCAO = 'backup_retencao';

    public const BACKUP_LIMITE_MB = 'backup_limite_mb';

    public const BACKUP_INTERVALO_DIAS = 'backup_intervalo_dias';

    public const BACKUP_AVISO_PERCENTUAL = 'backup_aviso_percentual';

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
        self::BACKUP_RETENCAO => [
            'rotulo' => 'Cópias mantidas por empresa',
            'ajuda' => 'Quantos back‑ups automáticos ficam no perfil. As cópias mais antigas saem sozinhas; '
                .'vazio usa o padrão da casa: 7.',
            'padrao' => 7,
            'regras' => ['nullable', 'integer', 'between:1,30'],
        ],
        self::BACKUP_INTERVALO_DIAS => [
            'rotulo' => 'Intervalo do back‑up automático (dias)',
            'ajuda' => 'De quantos em quantos dias uma cópia nova é gravada sem clique. A primeira já nasce '
                .'com a empresa aprovada; vazio usa o padrão da casa: 1 dia.',
            'padrao' => 1,
            'regras' => ['nullable', 'integer', 'between:1,30'],
        ],
        self::BACKUP_LIMITE_MB => [
            'rotulo' => 'Limite de armazenamento do back‑up (MB)',
            'ajuda' => 'Teto das cópias desta empresa. Atingido o limite, a gravação nova é recusada e o '
                .'aviso aparece no perfil; nenhum arquivo alheio é tocado. Padrão da casa: 20 MB.',
            'padrao' => 20,
            'regras' => ['nullable', 'integer', 'between:1,200'],
        ],
        self::BACKUP_AVISO_PERCENTUAL => [
            'rotulo' => 'Avisar ao usar percentual do limite (%)',
            'ajuda' => 'Quando as cópias chegam a esta fração do limite, o perfil mostra o aviso de carga '
                .'antes de faltar espaço. Padrão da casa: 80%.',
            'padrao' => 80,
            'regras' => ['nullable', 'integer', 'between:50,95'],
        ],
    ];

    /** @return array<string, string> */
    public static function chaves(): array
    {
        return array_column(self::PREFERENCIAS, 'rotulo');
    }
}
