{{-- Casca autenticada das etapas do cadastro de empresas. O fluxo mora em
     Cadastros › Empresas: fora do painel público, mas com a mesma identidade
     visual e a trilha do menu lateral. --}}
@props([
    'title',
    'subtitle' => null,
    'step' => 1,
])

@php
    $etapas = ['Dados da empresa', 'Confirmação', 'Documentos', 'Conclusão'];
@endphp

<x-layouts.app
    :title="$title"
    :subtitle="$subtitle"
    :trilha="['Cadastros' => null, 'Empresas' => route('cadastro.create'), $title => null]"
>
    <div class="nf-cadastro-card mx-auto">
        <div class="card">
            <div class="card-body">
                <ol class="nf-cadastro-etapas" aria-label="Etapas do cadastro">
                    @foreach ($etapas as $indice => $etapa)
                        <li @class(['ativo' => $indice + 1 === $step, 'concluido' => $indice + 1 < $step])>
                            <span aria-hidden="true">{{ $indice + 1 }}</span>
                            <span class="visually-hidden">{{ $etapa }}</span>
                        </li>
                    @endforeach
                </ol>

                <h2 class="nf-display nf-auth-title">{{ $title }}</h2>

                @if ($subtitle)
                    <p class="nf-auth-subtitle">{{ $subtitle }}</p>
                @endif

                {{ $slot }}
            </div>
        </div>
    </div>
</x-layouts.app>
