@use('App\Support\Formatters')

<x-layouts.app
    :title="$papel->name"
    :subtitle="$papel->is_system
        ? 'Papel de sistema: o alcance dele vem do catálogo e é regravado pela sincronização — a tela mostra, não edita.'
        : 'Papel personalizado: vive exatamente do que está marcado aqui embaixo.'"
    :trilha="['Gestão' => route('roles.index'), 'Papéis' => route('roles.index'), $papel->name => null]"
>
    <div class="nf-list-head">
        <p class="nf-list-head-titulo">
            <span class="nf-status {{ $papel->is_system ? 'nf-status-open' : 'nf-status-draft' }}">
                {{ $papel->is_system ? 'sistema' : 'personalizado' }}
            </span>
            · {{ $papel->permissions->count() }} {{ $papel->permissions->count() === 1 ? 'permissão' : 'permissões' }}
            · {{ $contas->count() }} {{ $contas->count() === 1 ? 'conta' : 'contas' }}
        </p>

        <div class="nf-list-acoes">
            <x-ui.button variant="ghost" size="sm" :href="route('roles.index')" icon="fa-solid fa-list">
                Voltar aos papéis
            </x-ui.button>

            @if (! $papel->is_system)
                @can('roles.update')
                    <x-ui.button variant="soft-primary" size="sm" :href="route('roles.edit', $papel)"
                        icon="fa-solid fa-pen">Editar papel</x-ui.button>
                @endcan

                @can('roles.delete')
                    <x-ui.action-form :acao="route('roles.destroy', $papel)" rotulo="Excluir"
                        titulo="Excluir o papel {{ $papel->name }}?"
                        texto="Só exclui papel que nenhuma conta usa. As permissões concedidas por ele somem junto." />
                @endcan
            @endif
        </div>
    </div>

    <div class="row g-3">
        <div class="col-12 col-xl-5">
            <x-ui.card title="Cadastro" subtitle="O que está gravado no banco desta empresa.">
                <dl class="nf-dl mb-0">
                    <div>
                        <dt>Nome</dt>
                        <dd>{{ $papel->name }}</dd>
                    </div>
                    <div>
                        <dt>Slug</dt>
                        <dd class="nf-mono">{{ $papel->slug }}</dd>
                    </div>
                    <div>
                        <dt>Descrição</dt>
                        <dd>{{ $papel->description ?: Formatters::TIME_NULL }}</dd>
                    </div>
                    <div>
                        <dt>Criado em</dt>
                        <dd class="nf-mono">{{ Formatters::dateTime($papel->created_at) }}</dd>
                    </div>
                </dl>
            </x-ui.card>

            <x-ui.card title="Contas com este papel" subtitle="Quem entra e enxerga isto." class="mt-3">
                @if ($contas->isEmpty())
                    <x-ui.state tone="empty" title="Nenhuma conta usa este papel"
                        text="Enquanto estiver vazio, o papel pode ser excluído sem deixar ninguém órfão." />
                @else
                    <ul class="nf-itens mb-0">
                        @foreach ($contas as $conta)
                            <li class="nf-item-linha nf-item-linha-lado">
                                <div>
                                    <p class="mb-0 fw-semibold">
                                        <a href="{{ route('users.show', $conta) }}">{{ $conta->name }}</a>
                                    </p>
                                    <p class="mb-0 nf-text-muted-2 small nf-mono">{{ $conta->email }}</p>
                                </div>
                                <span class="nf-mono">{{ Formatters::date($conta->last_login_at) }}</span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-ui.card>
        </div>

        <div class="col-12 col-xl-7">
            <x-ui.card title="Alcance" subtitle="O catálogo inteiro, com o que este papel tem aceso.">
                <ul class="nf-fact-list mb-0">
                    @foreach ($matriz as $linha)
                        @php($concedidas = array_column(array_filter($linha['acoes'], static fn (array $a) => $a['marcada']), 'rotulo'))
                        <li>
                            <span>{{ $linha['rotulo'] }}</span>
                            <strong>
                                {{ $concedidas === [] ? 'nada' : implode(' · ', $concedidas) }}
                            </strong>
                        </li>
                    @endforeach
                </ul>
            </x-ui.card>
        </div>
    </div>
</x-layouts.app>
