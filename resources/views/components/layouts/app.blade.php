{{-- Casca autenticada: AdminLTE 4 de estrutura, identidade do NEXUS-FIELD por
     cima. data-lte-color-mode="off" desliga o ColorMode do template porque a
     chave de tema do projeto é uma só e já decidiu o tema antes da pintura. --}}
@props([
    'title',
    'subtitle' => null,
    'trilha' => [],
])

@php($usuario = auth()->user())

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-lte-color-mode="off">
<head>
    <x-site-head :title="$title.' — '.config('app.name', 'NEXUS-FIELD')" :description="$subtitle" />
</head>
<body class="hold-transition sidebar-mini sidebar-expand-lg layout-fixed">
    <x-skip-links navigation />

    <div class="app-wrapper">
        <x-app.sidebar :user="$usuario" />
        <x-app.navbar :user="$usuario" />

        <main class="app-main" id="conteudo">
            <x-app.content-header :title="$title" :subtitle="$subtitle" :trilha="$trilha" />

            <div class="app-content">
                <div class="container-fluid nf-content">
                    {{ $slot }}
                </div>
            </div>
        </main>

        <x-app.footer />
    </div>

    <x-flash-messages />
</body>
</html>
