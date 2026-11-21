<?php

namespace App\Services\Organizacao;

use App\Models\Branch;
use App\Models\Department;
use App\Models\Employment;
use App\Models\Position;
use App\Support\Auditor;
use App\Support\Notifier;
use App\Services\Recusa;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Escrita da estrutura interna. Nenhum controller decide regra de negócio:
 * filial, setor, cargo e vínculo passam por aqui, dentro de transação, com a
 * trilha semântica gravada depois que a escrita foi silenciada na Auditable.
 *
 * O silenciamento não esconde auditoria: troca o par "criado/atualizado" pelo
 * verbo que o negócio recognhece ("filial criada", "vínculo movimentado").
 */
final class GestorDeEstrutura
{
    private const CAMPOS_FILIAL = ['name', 'slug', 'type', 'manager_user_id', 'active'];

    private const CAMPOS_SETOR = [
        'branch_id', 'parent_id', 'name', 'slug', 'description',
        'manager_user_id', 'type', 'depth', 'active',
    ];

    private const CAMPOS_CARGO = [
        'department_id', 'name', 'level', 'description', 'suggested_role_id', 'active',
    ];

    private const CAMPOS_VINCULO = [
        'user_id', 'branch_id', 'department_id', 'position_id', 'manager_id',
        'employee_code', 'employment_type', 'status', 'started_at', 'ended_at',
    ];

    private const PASSAGEM = [
        'user_id', 'branch_id', 'department_id', 'position_id', 'manager_id',
        'employee_code', 'employment_type', 'started_at',
    ];

    /** @param array<string, mixed> $dados */
    public function salvarFilial(array $dados, ?Branch $filial = null): Branch
    {
        $dados = $this->normalizar($dados);
        $criando = $filial === null;

        return DB::transaction(function () use ($dados, $filial, $criando): Branch {
            if (($dados['type'] ?? 'filial') === 'matriz') {
                $outra = Branch::query()
                    ->where('type', 'matriz')
                    ->where('active', true)
                    ->when($filial !== null, fn ($query) => $query->whereKeyNot($filial->getKey()))
                    ->exists();

                if ($outra) {
                    throw new Recusa('Já existe uma matriz ativa nesta empresa.');
                }
            }

            $entidade = $filial ?? new Branch;
            $antes = $criando ? [] : $entidade->only(self::CAMPOS_FILIAL);

            Auditor::silenciar(function () use ($entidade, $dados): void {
                $entidade->fill(Arr::only($dados, self::CAMPOS_FILIAL));
                $entidade->save();
            });

            $this->auditar(
                $criando ? 'filial criada' : 'filial atualizada',
                $entidade,
                $criando ? [] : $this->mudancas($antes, $entidade->only(self::CAMPOS_FILIAL)),
                $entidade->name,
            );

            return $entidade;
        });
    }

    /** @param array<string, mixed> $dados */
    public function excluirFilial(Branch $filial): void
    {
        DB::transaction(function () use ($filial): void {
            if ($filial->departments()->exists() || $filial->employments()->exists()) {
                throw new Recusa(
                    'Esta filial ainda tem setores ou vínculos. Movimente os vínculos e remova os setores antes de excluí-la.',
                );
            }

            $nome = $filial->name;

            Auditor::silenciar(fn () => $filial->delete());
            Auditor::gravar('filial excluída', $filial, [], "Filial “{$nome}” saiu da estrutura.");
        });
    }

    /** @param array<string, mixed> $dados */
    public function salvarSetor(array $dados, ?Department $setor = null): Department
    {
        $dados = $this->normalizar($dados);
        $criando = $setor === null;

        return DB::transaction(function () use ($dados, $setor, $criando): Department {
            $entidade = $setor ?? new Department;
            $antes = $criando ? [] : $entidade->only(self::CAMPOS_SETOR);
            $pathAnterior = (string) $entidade->path;

            $filialId = array_key_exists('branch_id', $dados)
                ? $dados['branch_id']
                : $entidade->branch_id;
            $paiId = array_key_exists('parent_id', $dados)
                ? $dados['parent_id']
                : $entidade->parent_id;

            if ($paiId !== null && $filialId !== null) {
                $pai = Department::query()->find((int) $paiId);

                if ($pai !== null && $pai->branch_id !== null && (int) $pai->branch_id !== (int) $filialId) {
                    throw new Recusa('O setor pai pertence a outra filial desta empresa.');
                }
            }

            Auditor::silenciar(function () use ($entidade, $dados): void {
                $entidade->fill(Arr::only($dados, self::CAMPOS_SETOR));
                $entidade->save();
            });

            // O refresh abaixo limpa `getChanges()`: o "antes e depois" já foi
            // capturado antes do save e não depende mais do estado do modelo.
            $entidade->refresh();
            $pai = $entidade->parent_id === null ? null : Department::query()->find($entidade->parent_id);
            $novoPath = ($pai?->path ?: '/').$entidade->getKey().'/';

            if ($pathAnterior !== $novoPath && ! $criando) {
                $this->recalcularSubarvore($entidade, $pathAnterior, $novoPath);
            }

            $this->auditar(
                $criando ? 'setor criado' : 'setor atualizado',
                $entidade,
                $criando ? [] : $this->mudancas($antes, $entidade->only(self::CAMPOS_SETOR)),
                $entidade->name,
            );

            return $entidade;
        });
    }

    /** @param array<string, mixed> $dados */
    public function excluirSetor(Department $setor): void
    {
        DB::transaction(function () use ($setor): void {
            if ($setor->children()->exists() || $setor->employments()->exists() || $setor->positions()->exists()) {
                throw new Recusa(
                    'Este setor ainda tem setores filhos, cargos ou vínculos. Movimente o que depende dele antes de excluí-lo.',
                );
            }

            $nome = $setor->name;

            Auditor::silenciar(fn () => $setor->delete());
            Auditor::gravar('setor excluído', $setor, [], "Setor “{$nome}” saiu da árvore.");
        });
    }

    /** @param array<string, mixed> $dados */
    public function salvarCargo(array $dados, ?Position $cargo = null): Position
    {
        $dados = $this->normalizar($dados);
        $criando = $cargo === null;

        return DB::transaction(function () use ($dados, $cargo, $criando): Position {
            $entidade = $cargo ?? new Position;
            $antes = $criando ? [] : $entidade->only(self::CAMPOS_CARGO);

            Auditor::silenciar(function () use ($entidade, $dados): void {
                $entidade->fill(Arr::only($dados, self::CAMPOS_CARGO));
                $entidade->save();
            });

            $this->auditar(
                $criando ? 'cargo criado' : 'cargo atualizado',
                $entidade,
                $criando ? [] : $this->mudancas($antes, $entidade->only(self::CAMPOS_CARGO)),
                $entidade->name,
            );

            return $entidade;
        });
    }

    /** @param array<string, mixed> $dados */
    public function excluirCargo(Position $cargo): void
    {
        DB::transaction(function () use ($cargo): void {
            if ($cargo->employments()->exists()) {
                throw new Recusa(
                    'Este cargo ainda está em vínculos. Troque o cargo dessas pessoas antes de excluí-lo.',
                );
            }

            $nome = $cargo->name;

            Auditor::silenciar(fn () => $cargo->delete());
            Auditor::gravar('cargo excluído', $cargo, [], "Cargo “{$nome}” saiu do catálogo da empresa.");
        });
    }

    /**
     * Vínculo nunca é reescrito: se a passagem mudou, a linha atual é encerrada
     * e uma nova nasce. Só estado e data de término são ajustados no lugar.
     *
     * @param  array<string, mixed>  $dados
     */
    public function salvarVinculo(array $dados, ?Employment $vinculo = null): Employment
    {
        $dados = $this->normalizar($dados);
        $criando = $vinculo === null;

        return DB::transaction(function () use ($dados, $vinculo, $criando): Employment {
            if ($criando) {
                $entidade = new Employment;
                $valores = array_merge(['status' => 'ativo', 'ended_at' => null], Arr::only($dados, self::CAMPOS_VINCULO));

                Auditor::silenciar(function () use ($entidade, $valores): void {
                    $entidade->fill($valores);
                    $entidade->save();
                });

                $this->auditar('vínculo criado', $entidade, [], $entidade->employee_code);
                $this->notificar($entidade, 'Vínculo registrado', 'Você passou a constar na estrutura organizacional.');

                return $entidade;
            }

            $entidade = $vinculo;
            $mudouPassagem = false;

            foreach (self::PASSAGEM as $campo) {
                if (! array_key_exists($campo, $dados)) {
                    continue;
                }

                // `started_at` é Carbon no modelo e string no formulário: sem
                // converter os dois para a mesma forma, toda edição pareceria
                // mudança de passagem e encerraria o vínculo à toa.
                $antes = $campo === 'started_at'
                    ? $entidade->started_at?->toDateString()
                    : $entidade->{$campo};

                if ((string) $antes !== (string) ($dados[$campo] ?? '')) {
                    $mudouPassagem = true;
                    break;
                }
            }

            if (! $mudouPassagem) {
                $camposEstado = ['status', 'ended_at'];
                $antes = $entidade->only($camposEstado);
                Auditor::silenciar(fn () => $entidade->update(Arr::only($dados, $camposEstado)));
                $this->auditar(
                    'vínculo atualizado',
                    $entidade,
                    $this->mudancas($antes, $entidade->only($camposEstado)),
                    $entidade->employee_code,
                );

                return $entidade;
            }

            $valores = array_merge($entidade->only(self::CAMPOS_VINCULO), $dados);

            // A linha encerrada guarda o código original: a nova passagem precisa
            // de um código próprio para não ferir o UNIQUE da empresa.
            if ((string) ($dados['employee_code'] ?? '') === (string) $entidade->employee_code) {
                $valores['employee_code'] = $this->proximoCodigo(
                    (string) $entidade->employee_code,
                    (int) $entidade->company_id,
                );
            }

            $valores['status'] = $dados['status'] ?? 'ativo';
            $valores['ended_at'] = null;

            $anteriorId = (int) $entidade->getKey();
            Auditor::silenciar(fn () => $entidade->encerrar('encerrado'));

            $nova = new Employment;

            Auditor::silenciar(function () use ($nova, $valores): void {
                $nova->fill($valores);
                $nova->save();
            });

            Auditor::gravar(
                'vínculo movimentado',
                $nova,
                [],
                sprintf(
                    'Passagem #%d encerrada; nova passagem #%d aberta para %s.',
                    $anteriorId,
                    (int) $nova->getKey(),
                    $nova->employee_code,
                ),
            );
            $this->notificar($nova, 'Vínculo atualizado', 'Sua filial, setor, cargo ou gestor foi atualizado.');

            return $nova;
        });
    }

    public function desligarVinculo(Employment $vinculo): Employment
    {
        return DB::transaction(function () use ($vinculo): Employment {
            if (in_array($vinculo->status, ['desligado', 'encerrado'], true)) {
                throw new Recusa('Esta passagem já foi encerrada.');
            }

            $codigo = $vinculo->employee_code;

            Auditor::silenciar(fn () => $vinculo->encerrar('desligado'));
            Auditor::gravar(
                'vínculo desligado',
                $vinculo,
                [],
                "Passagem “{$codigo}” encerrada sem apagar o histórico.",
            );
            $this->notificar($vinculo, 'Vínculo desligado', 'Sua passagem na estrutura organizacional foi encerrada.');

            return $vinculo;
        });
    }

    /** @param array<string, mixed> $dados @return array<string, mixed> */
    private function normalizar(array $dados): array
    {
        foreach ([
            'manager_user_id', 'branch_id', 'parent_id', 'department_id',
            'position_id', 'suggested_role_id', 'ended_at',
        ] as $campo) {
            if (array_key_exists($campo, $dados) && ($dados[$campo] === '' || $dados[$campo] === null)) {
                $dados[$campo] = null;
            }
        }

        if (array_key_exists('active', $dados)) {
            $dados['active'] = filter_var($dados['active'], FILTER_VALIDATE_BOOLEAN);
        }

        if (! empty($dados['started_at'])) {
            $dados['started_at'] = Carbon::parse((string) $dados['started_at'])->toDateString();
        }

        return $dados;
    }

    private function recalcularSubarvore(Department $setor, string $pathAnterior, string $novoPath): void
    {
        $setor->forceFill(['path' => $novoPath])->saveQuietly();

        Department::query()
            ->whereKeyNot($setor->getKey())
            ->where('path', 'like', $pathAnterior.'%')
            ->orderBy('path')
            ->get()
            ->each(function (Department $descendente) use ($pathAnterior, $novoPath): void {
                $novoPathDescendente = $novoPath.substr((string) $descendente->path, strlen($pathAnterior));

                $descendente->forceFill([
                    'path' => $novoPathDescendente,
                    'depth' => substr_count(trim($novoPathDescendente, '/'), '/'),
                ])->saveQuietly();
            });
    }

    private function proximoCodigo(string $base, int $empresa): string
    {
        $tentativa = $base;
        $sequencia = 2;

        while (Employment::withTrashed()
            ->where('company_id', $empresa)
            ->where('employee_code', $tentativa)
            ->exists()) {
            $tentativa = substr($base, 0, 28).'-'.$sequencia;
            $sequencia++;
        }

        return $tentativa;
    }

    /**
     * Diff de campo a campo feito antes da escrita. O `getOriginal()` do Eloquent
     * já vem sincronizado depois do `save()`, então compará-lo ali devolve vazio
     * e a trilha perde o "antes e depois" de cada alteração.
     *
     * @param  array<string, mixed>  $antes
     * @param  array<string, mixed>  $depois
     * @return array<string, array{0: mixed, 1: mixed}>
     */
    private function mudancas(array $antes, array $depois): array
    {
        $mudancas = [];

        foreach ($depois as $campo => $valor) {
            if (! array_key_exists($campo, $antes)) {
                continue;
            }

            if ((string) $antes[$campo] !== (string) $valor) {
                $mudancas[$campo] = [$antes[$campo], $valor];
            }
        }

        return $mudancas;
    }

    /** @param array<string, array{0: mixed, 1: mixed}> $mudancas */
    private function auditar(string $acao, $entidade, array $mudancas, string $resumo): void
    {
        Auditor::gravar($acao, $entidade, $mudancas, $resumo);
    }

    private function notificar(Employment $vinculo, string $titulo, string $corpo): void
    {
        $destino = $vinculo->user;

        if ($destino === null || $destino->status !== 'active') {
            return;
        }

        Notifier::para(
            $destino,
            'organizacao.vinculo',
            $titulo,
            $corpo,
            route('organization.employments.index'),
            ['vinculo_id' => (int) $vinculo->getKey()],
        );
    }
}
