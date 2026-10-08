{{-- Cabeçalho de conteúdo do AdminLTE 4 (.app-content-header dentro de
     .app-main): título da tela, subtitle e trilha de navegação. --}}
@props([
    'title',
    'subtitle' => null,
    'trilha' => [],
])

<div class="app-content-header">
    <div class="container-fluid nf-content">
        <div class="nf-content-header-row">
            <div class="nf-content-header-title">
                <h1 class="h4 mb-0">{{ $title }}</h1>

                @if ($subtitle)
                    <p class="nf-content-subtitle">{{ $subtitle }}</p>
                @endif
            </div>

            @if ($trilha !== [])
                <nav aria-label="Trilha de navegação">
                    <ol class="breadcrumb">
                        @foreach ($trilha as $rotulo => $url)
                            @if ($url === null || $loop->last)
                                <li class="breadcrumb-item active" aria-current="page">{{ $rotulo }}</li>
                            @else
                                <li class="breadcrumb-item"><a href="{{ $url }}">{{ $rotulo }}</a></li>
                            @endif
                        @endforeach
                    </ol>
                </nav>
            @endif
        </div>
    </div>
</div>
