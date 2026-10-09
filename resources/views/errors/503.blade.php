{{-- 503: ponte levantada. A página precisa viver sem banco, sem sessão e sem rota. --}}
@php
    // Em manutenção nada pode depender de rota, sessão, Vite ou banco: esta página é
    // a única coisa de pé enquanto a casa arruma a máquina. Por isso ela não usa a
    // casca compartilhada — desenha sozinha, com os verdes da marca em valor fixo.
    $retry = $retry ?? null;
@endphp
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="A plataforma está em manutenção programada e volta em instantes.">
    <title>Manutenção programada — NEXUS-FIELD</title>
    <style>
        :root { color-scheme: dark; }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            min-height: 100vh;
            display: grid;
            place-items: center;
            background: #000;
            color: #c9f9b6;
            font-family: ui-sans-serif, system-ui, "Segoe UI", sans-serif;
            text-align: center;
            padding: 2rem;
        }
        main { max-width: 34rem; }
        .marca {
            width: 72px; height: 72px; margin: 0 auto 1.5rem;
            border-radius: 18px;
            background: linear-gradient(145deg, #0d1a0d, #000);
            border: 1px solid rgba(98, 188, 96, .4);
            display: grid; place-items: center;
            font-weight: 700; font-size: 1.75rem; color: #62bc60;
        }
        h1 { font-size: 1.6rem; letter-spacing: .01em; margin-bottom: .75rem; color: #e7ffd9; }
        p { color: #9fc794; line-height: 1.6; margin-bottom: .5rem; }
        .espera { margin-top: 1.25rem; font-size: .9rem; color: #6f8f66; }
    </style>
</head>
<body>
    <main>
        <div class="marca" aria-hidden="true">N</div>
        <h1>A casa está em manutenção</h1>
        <p>
            Subimos a ponte para trocar uma peça. Nenhum pedido é perdido no escuro:
            reabra a plataforma em instantes.
        </p>
        @if (is_int($retry) && $retry > 0)
            <p class="espera">Nova tentativa automática em {{ $retry }} segundos.</p>
            <meta http-equiv="refresh" content="{{ $retry }}">
        @endif
    </main>
</body>
</html>
