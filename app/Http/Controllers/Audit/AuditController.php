<?php

namespace App\Http\Controllers\Audit;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use App\Support\Export;
use App\Support\Formatters;
use App\Support\ListFilters;
use App\Support\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * A trilha de auditoria por tela: consulta, ficha e exportação. O registro é
 * escrito por `Auditor` na hora do ato — aqui ele só se lê. Nada nesta tela
 * edita ou apaga linha: o que aconteceu não se reescreve, se acrescenta, e a
 * prova disso é que não existe rota de escrita nenhuma.
 *
 * O alcance é o tenant: cada empresa enxerga só os próprios atos, pelo escopo
 * global do modelo. Ver é degrau de gestão (`audit.view` — supervisor e
 * administrador); exportar tem degrau próprio (`audit.export`), e o CSV nasce
 * da MESMA consulta da listagem, com os mesmos filtros da query string.
 */
class AuditController extends Controller
{
    private const ORDENAVEIS = ['created_at', 'user_name', 'action', 'entity_type'];

    private const FILTROS = ['busca', 'entidade', 'quem', 'inicio', 'fim'];

    public function index(Request $request): View
    {
        return view('audit.index', [
            'atos' => $this->consulta($request)
                ->orderBy(...ListFilters::ordenacaoAtual($request, self::ORDENAVEIS, 'created_at', 'desc'))
                ->paginate(ListFilters::porPagina($request))
                ->withQueryString(),
            'entidades' => $this->entidadesVistas(),
            'quem' => $this->contasDaEmpresa(),
            'filtrosAtivos' => ListFilters::ativos($request, self::FILTROS),
        ]);
    }

    public function show(AuditLog $ato): View
    {
        $this->garantirDaEmpresa($ato);

        return view('audit.show', [
            'ato' => $ato->load('user'),
            'mudancas' => AuditLog::mudancas($ato),
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        return Export::csv(
            'auditoria',
            ['Quando', 'Quem', 'Ação', 'Sobre', 'Registro', 'Descrição', 'O que mudou', 'IP'],
            $this->consulta($request)
                ->orderBy(...ListFilters::ordenacaoAtual($request, self::ORDENAVEIS, 'created_at', 'desc'))
                ->lazyById(200)
                ->map($this->linhaCsv(...)),
        );
    }

    /** @return array<int, mixed> */
    private function linhaCsv(AuditLog $ato): array
    {
        return [
            $ato->created_at === null ? '' : Formatters::dateTime($ato->created_at),
            $ato->user_name,
            $ato->action,
            AuditLog::rotuloEntidade($ato->entity_type),
            $ato->entity_id,
            $ato->description,
            $this->resumoMudancas(AuditLog::mudancas($ato)),
            $ato->ip_address,
        ];
    }

    /**
     * A consulta da listagem é a consulta da exportação: filtro que vale na
     * tela vale no CSV, porque ambos montam o mesmo Builder a partir da mesma
     * query string.
     *
     * @return Builder<AuditLog>
     */
    private function consulta(Request $request): Builder
    {
        $query = ListFilters::busca(
            AuditLog::query()->where('company_id', TenantContext::id()),
            $request,
            ['user_name', 'action', 'description', 'ip_address'],
        );

        $query = ListFilters::igual($query, $request, 'entidade', 'entity_type', array_keys($this->entidadesVistas()));
        $query = ListFilters::relacionado($query, $request, 'quem', 'user', 'id');

        return ListFilters::periodo($query, $request, 'created_at');
    }

    /**
     * As opções do filtro de entidade nascem da própria trilha desta empresa:
     * só entra na lista o tipo que tem ato de verdade, na ordem que ele ocupa
     * no histórico.
     *
     * @return array<string, string>
     */
    private function entidadesVistas(): array
    {
        return AuditLog::query()
            ->whereNotNull('entity_type')
            ->distinct()
            ->orderBy('entity_type')
            ->pluck('entity_type')
            ->mapWithKeys(fn (string $tipo) => [$tipo => AuditLog::rotuloEntidade($tipo)])
            ->all();
    }

    /** @return array<int, string> id => nome, das contas vivas desta empresa */
    private function contasDaEmpresa(): array
    {
        return User::query()
            ->where('company_id', TenantContext::id())
            ->whereNull('deleted_at')
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    /**
     * Diff legível para o CSV: `campo: antes -> depois`, separado por ponto
     * intermediário. O texto cru do diff não é dado de exportação.
     *
     * @param  array<string, array{0: mixed, 1: mixed}>  $mudancas
     */
    private function resumoMudancas(array $mudancas): string
    {
        return implode(' · ', array_map(
            fn (string $campo, array $par) => sprintf(
                '%s: %s -> %s',
                $campo,
                AuditLog::pintarValor($par[0] ?? null),
                AuditLog::pintarValor($par[1] ?? null),
            ),
            array_keys($mudancas),
            $mudancas,
        ));
    }

    /** O ato do outro tenant não existe para esta tela — nem como 403 com explicação. */
    private function garantirDaEmpresa(AuditLog $ato): void
    {
        abort_unless((int) $ato->company_id === (int) TenantContext::id(), 404);
    }
}
