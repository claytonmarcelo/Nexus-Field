{{-- Casca das etapas do cadastro público de empresa. --}}
@props([
    'title',
    'subtitle' => null,
    'step' => 1,
])

@php
    $etapas = ['Dados da empresa', 'Confirmação', 'Documentos', 'Conclusão'];
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <x-site-head :title="$title.' — '.config('app.name', 'NEXUS-FIELD')" :description="$subtitle" />
</head>
<body>
    <x-skip-links />

    <div class="nf-public">
        <x-public-header />

        <main class="nf-auth nf-cadastro" id="conteudo">
            <div class="nf-auth-card nf-cadastro-card">
                <div class="nf-auth-brand">
                    <x-ui.brand-mark arquivo="marca-nexus-128" lado="128" animacao="nivelando" />
                    <span class="nf-auth-brand-nivel" aria-hidden="true"></span>
                </div>

                <ol class="nf-cadastro-etapas" aria-label="Etapas do cadastro">
                    @foreach ($etapas as $indice => $etapa)
                        <li @class(['ativo' => $indice + 1 === $step, 'concluido' => $indice + 1 < $step])>
                            <span aria-hidden="true">{{ $indice + 1 }}</span>
                            <span class="visually-hidden">{{ $etapa }}</span>
                        </li>
                    @endforeach
                </ol>

                <h1 class="nf-display nf-auth-title">{{ $title }}</h1>

                @if ($subtitle)
                    <p class="nf-auth-subtitle">{{ $subtitle }}</p>
                @endif

                {{ $slot }}
            </div>
        </main>

        <x-public-footer />
    </div>

    <x-flash-messages />
</body>
</html>
