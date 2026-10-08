<x-layouts.app
    title="Dashboard"
    subtitle="Sessão, empresa e o que já existe de base."
>
    <div class="row g-3">
        <div class="col-12 col-xl-7">
            <x-ui.card title="Quem está operando" subtitle="Tudo abaixo vem da sessão e do cadastro da empresa.">
                <div class="d-flex flex-wrap gap-3 align-items-start">
                    <span class="nf-avatar nf-avatar-lg" aria-hidden="true">{{ $usuario->initials() }}</span>

                    <div class="flex-grow-1 nf-session">
                        <dl class="nf-dl mb-0">
                            <div>
                                <dt>Nome</dt>
                                <dd>{{ $usuario->name }}</dd>
                            </div>
                            <div>
                                <dt>E-mail</dt>
                                <dd class="nf-mono">{{ $usuario->email }}</dd>
                            </div>
                            <div>
                                <dt>Papel</dt>
                                <dd>
                                    @forelse ($usuario->roles as $papel)
                                        <span class="badge text-bg-primary">{{ $papel->name }}</span>
                                    @empty
                                        <span class="badge text-bg-secondary">Sem papel atribuído</span>
                                    @endforelse
                                </dd>
                            </div>
                            <div>
                                <dt>Empresa</dt>
                                <dd>{{ $usuario->company?->name ?? 'Sem empresa vinculada' }}</dd>
                            </div>
                            @if ($usuario->company?->plan)
                                <div>
                                    <dt>Plano</dt>
                                    <dd>{{ $usuario->company->plan->name }}</dd>
                                </div>
                            @endif
                            <div>
                                <dt>Último acesso</dt>
                                <dd class="nf-mono">
                                    @if ($usuario->last_login_at)
                                        {{ $usuario->last_login_at->format('d/m/Y H:i') }}
                                        <span class="nf-text-muted-2">{{ $usuario->last_login_at->diffForHumans() }}</span>
                                    @else
                                        <span class="nf-text-muted-2">Primeiro acesso desta conta.</span>
                                    @endif
                                </dd>
                            </div>
                        </dl>
                    </div>
                </div>

                <x-slot:tools>
                    <span class="nf-status nf-status-done">Conta ativa</span>
                </x-slot:tools>
            </x-ui.card>
        </div>

        <div class="col-12 col-xl-5">
            <x-ui.card title="Base consultada" subtitle="Contagens reais no MySQL desta empresa.">
                <ul class="nf-fact-list mb-0">
                    <li>
                        <span>Usuários com acesso</span>
                        <strong class="nf-mono">{{ $base['usuarios'] }}</strong>
                    </li>
                    <li>
                        <span>Clientes cadastrados</span>
                        <strong class="nf-mono">{{ $base['clientes'] }}</strong>
                    </li>
                    <li>
                        <span>Papéis da empresa</span>
                        <strong class="nf-mono">{{ $base['papeis'] }}</strong>
                    </li>
                    <li>
                        <span>Permissões no catálogo</span>
                        <strong class="nf-mono">{{ $base['permissoes'] }}</strong>
                    </li>
                </ul>

                <x-slot:footer>
                    <p class="mb-0 nf-text-muted-2">
                        Os indicadores operacionais aparecem conforme os módulos que os
                        produzem entram no sistema — nada de número antes do dado existir.
                    </p>
                </x-slot:footer>
            </x-ui.card>
        </div>
    </div>
</x-layouts.app>
