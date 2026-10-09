@use('App\Support\Formatters')

<x-layouts.app
    :title="$ato->action"
    :subtitle="'Um carimbo da trilha: quem fez, em quê, de onde — e o valor de antes e de depois, quando havia.'"
    :trilha="['Gestão' => route('audit.index'), 'Auditoria' => route('audit.index'), 'Ato' => null]"
>
    <div class="nf-list-head">
        <p class="nf-list-head-titulo">
            <span class="nf-status nf-status-draft">{{ $ato->action }}</span>
            @if ($ato->user !== null && $ato->user->is(request()->user()))
                <span class="nf-status nf-status-done ms-2">é você</span>
            @endif
        </p>

        <div class="nf-list-acoes">
            <x-ui.button variant="ghost" size="sm" :href="route('audit.index')" icon="fa-solid fa-list">
                Voltar à trilha
            </x-ui.button>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-12 col-xl-7">
            <x-ui.card title="O ato" subtitle="Congelado na hora em que aconteceu — por isso o nome do autor sobrevive a ele.">
                <dl class="nf-dl mb-0">
                    <div>
                        <dt>Quando</dt>
                        <dd class="nf-mono">{{ $ato->created_at ? Formatters::dateTime($ato->created_at) : '—' }}</dd>
                    </div>
                    <div>
                        <dt>Quem</dt>
                        <dd>
                            {{ $ato->user_name ?: 'sem autor' }}
                            @if ($ato->user === null && $ato->user_id !== null)
                                <div class="nf-text-muted-2">a conta que fez isso saiu do acesso</div>
                            @endif
                        </dd>
                    </div>
                    <div>
                        <dt>Ação</dt>
                        <dd>{{ $ato->action }}</dd>
                    </div>
                    <div>
                        <dt>Sobre</dt>
                        <dd>
                            {{ \App\Models\AuditLog::rotuloEntidade($ato->entity_type) }}
                            @if ($ato->entity_id !== null)
                                <span class="nf-mono">#{{ $ato->entity_id }}</span>
                            @endif
                        </dd>
                    </div>
                    <div>
                        <dt>Descrição</dt>
                        <dd>{{ $ato->description ?: '—' }}</dd>
                    </div>
                </dl>
            </x-ui.card>
        </div>

        <div class="col-12 col-xl-5">
            <x-ui.card title="De onde" subtitle="A origem do pedido, do jeito que o servidor a recebeu.">
                <dl class="nf-dl mb-0">
                    <div>
                        <dt>IP</dt>
                        <dd class="nf-mono">{{ $ato->ip_address ?: '—' }}</dd>
                    </div>
                    <div>
                        <dt>Navegador</dt>
                        <dd class="small">{{ $ato->user_agent ?: '—' }}</dd>
                    </div>
                </dl>
            </x-ui.card>
        </div>

        <div class="col-12">
            <x-ui.card title="O que mudou" subtitle="Antes e depois, campo a campo, como o servidor viu.">
                @if ($mudancas === [])
                    <x-ui.state tone="empty" title="Sem valores alterados"
                        text="Este ato não carregava diff — a descrição de cima conta o que houve.">
                    </x-ui.state>
                @else
                    <div class="table-responsive">
                        <table class="table nf-table align-middle mb-0">
                            <caption class="visually-hidden">Valores de antes e de depois registrados neste ato</caption>
                            <thead>
                                <tr>
                                    <th scope="col">Campo</th>
                                    <th scope="col">Antes</th>
                                    <th scope="col">Depois</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($mudancas as $campo => $par)
                                    <tr>
                                        <td class="nf-mono">{{ $campo }}</td>
                                        <td>{{ \App\Models\AuditLog::pintarValor($par[0] ?? null) }}</td>
                                        <td>{{ \App\Models\AuditLog::pintarValor($par[1] ?? null) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </x-ui.card>
        </div>
    </div>
</x-layouts.app>
