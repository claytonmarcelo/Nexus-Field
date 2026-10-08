{{-- Casca das telas abertas de acesso: login e recuperação de senha. --}}
@props([
    'title',
    'subtitle' => null,
    'withLogin' => false,
])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <x-site-head :title="$title.' — '.config('app.name', 'NEXUS-FIELD')" :description="$subtitle" />
</head>
<body>
    <x-skip-links />

    <div class="nf-public">
        <x-public-header :with-login="$withLogin" />

        <main class="nf-auth" id="conteudo">
            <div class="nf-auth-card">
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
