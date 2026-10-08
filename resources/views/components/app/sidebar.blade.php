{{-- Sidebar no contrato do AdminLTE 4: .app-sidebar > .sidebar-brand +
     .sidebar-wrapper > .sidebar-menu[data-lte-toggle="treeview"]. O estado
     (recolhida/aberta) vive no <body>, quem muda é o botão do navbar. --}}
@props(['user'])

@php
    $secoes = \App\Support\Navigation::for($user);
@endphp

<aside class="app-sidebar" data-bs-theme="dark">
    <div class="sidebar-brand">
        <a class="brand-link" href="{{ route('welcome') }}" aria-label="NEXUS-FIELD, página inicial">
            <x-ui.brand-mark />
            <span class="brand-text">
                <span class="nf-wordmark-lead">NEXUS</span><span class="nf-wordmark-tail">-FIELD</span>
            </span>
        </a>
    </div>

    <div class="sidebar-wrapper">
        <nav id="navigation" class="mt-2" aria-label="Menu principal">
            <ul class="nav sidebar-menu flex-column" data-lte-toggle="treeview" data-accordion="false">
                @foreach ($secoes as $secao)
                    <li class="nav-header">{{ $secao['label'] }}</li>

                    @foreach ($secao['items'] as $item)
                        <li class="nav-item">
                            <a href="{{ $item['url'] }}" class="nav-link{{ $item['active'] ? ' active' : '' }}"
                                @if ($item['active']) aria-current="page" @endif>
                                <i class="nav-icon {{ $item['icon'] }}" aria-hidden="true"></i>
                                <p>{{ $item['label'] }}</p>
                            </a>
                        </li>
                    @endforeach
                @endforeach
            </ul>
        </nav>
    </div>
</aside>
