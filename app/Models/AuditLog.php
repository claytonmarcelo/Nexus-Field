<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * Linha da trilha de auditoria. Não é soft-delete e não tem updated_at: o que
 * aconteceu não se reescreve, se acrescenta.
 */
class AuditLog extends Model
{
    use BelongsToCompany;

    public $timestamps = false;

    protected $fillable = [
        'company_id', 'user_id', 'user_name', 'action', 'entity_type', 'entity_id',
        'description', 'changes', 'ip_address', 'user_agent', 'created_at',
    ];

    protected function casts(): array
    {
        return [
            'changes' => 'array',
            'created_at' => 'datetime',
        ];
    }

    /**
     * Nome de modelo não é palavra da casa. O `class_basename` gravado por
     * `Auditor` vira aqui o substantivo que a gestão reconhece; o que não
     * estiver na lista é devolvido legível pelo `Str::headline`, nunca em
     * código-fonte.
     */
    private const ENTIDADES = [
        'Address' => 'Endereço',
        'Appointment' => 'Compromisso de agenda',
        'Client' => 'Cliente',
        'ClientContact' => 'Contato de cliente',
        'Company' => 'Empresa',
        'CompanySetting' => 'Preferência da empresa',
        'FinancialRecord' => 'Lançamento financeiro',
        'Notification' => 'Notificação',
        'Payment' => 'Pagamento',
        'Product' => 'Produto',
        'Role' => 'Papel',
        'Service' => 'Serviço',
        'ServiceCategory' => 'Categoria de serviço',
        'ServiceOrder' => 'Ordem de serviço',
        'ServiceOrderCheckin' => 'Check-in de ordem',
        'Specialty' => 'Especialidade',
        'StockMovement' => 'Movimentação de estoque',
        'Team' => 'Equipe',
        'Technician' => 'Técnico',
        'Ticket' => 'Chamado',
        'TicketComment' => 'Comentário de chamado',
        'User' => 'Conta de acesso',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function rotuloEntidade(?string $tipo): string
    {
        if ($tipo === null || $tipo === '') {
            return 'a própria operação';
        }

        return self::ENTIDADES[$tipo] ?? Str::headline($tipo);
    }

    /**
     * O diff como a tela o consome: `campo => [antes, depois]`. O cast `array`
     * devolve a forma de hoje; linhas antigas, escritas quando o `Auditor`
     * codificava o JSON duas vezes, chegam como string e são traduzidas aqui.
     * Esta é a única leitura tolerante da casa — quem consulta a trilha lê
     * valores, nunca JSON cru.
     *
     * @return array<string, array{0: mixed, 1: mixed}>
     */
    public static function mudancas(self $ato): array
    {
        // $ato->changes, dentro do escopo da classe, resolve a propriedade
        // protegida $changes do dirty-tracking do Eloquent — nunca a coluna
        // com cast. getAttribute() é a única porta que enxerga o cast.
        $changes = $ato->getAttribute('changes');

        if (is_string($changes) && $changes !== '') {
            $changes = json_decode($changes, true);
        }

        if (! is_array($changes)) {
            return [];
        }

        return array_map(
            fn (mixed $par) => is_array($par) ? array_values($par) : [$par, null],
            $changes,
        );
    }

    /** Um valor de diff virando texto honesto: nulo é traço, booleano é sim/não. */
    public static function pintarValor(mixed $valor): string
    {
        if ($valor === null || $valor === '') {
            return '—';
        }

        if (is_bool($valor)) {
            return $valor ? 'sim' : 'não';
        }

        if (is_array($valor)) {
            return Str::limit(json_encode($valor, JSON_UNESCAPED_UNICODE), 120, '…');
        }

        return (string) $valor;
    }
}
