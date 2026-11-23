{{-- Casca das telas abertas de acesso: cadastro, login, recuperação, redefinição
     e páginas de erro. Segue o padrão do site de referência: barra enxuta com
     marca, voltar e tema, cartão único com medalhão, título e subtítulo, sem rodapé. --}}
@props([
    'title',
    'subtitle' => null,
    'back' => null,
    'label' => 'Voltar',
])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <x-site-head :title="$title.' — '.config('app.name', 'NEXUS-FIELD')" :description="$subtitle" />
</head>
<body>
    <x-skip-links />

    <div class="nf-public">
        <x-auth-topbar :back="$back ?? route('welcome')" :label="$label" />

        <main class="nf-auth" id="conteudo">
            <div class="nf-auth-card">
                {{-- O medalhão abre o cartão, como nas telas de acesso de referência.
                     É informação visual: o nome da página já está no título. --}}
                <div class="nf-auth-marca" aria-hidden="true">
                    <x-ui.brand-mark lado="64" />
                </div>

                <h1 class="nf-display nf-auth-title">{{ $title }}</h1>

                @if ($subtitle)
                    <p class="nf-auth-subtitle">{{ $subtitle }}</p>
                @endif

                {{ $slot }}
            </div>
        </main>
    </div>

    <x-flash-messages />
</body>
</html>
