{{--
    Marca da plataforma: o medalhão da logo oficial, no mesmo arquivo em toda a
    interface. `arquivo`/`lado` escolhem a resolução nativa; `animacao` liga o
    movimento de auto nível das telas abertas.
--}}
@props([
    'arquivo' => 'marca-nexus-64',
    'lado' => 64,
    'animacao' => null,
])

<img
    class="nf-brand-mark{{ $animacao ? ' nf-brand-mark--'.$animacao : '' }}"
    src="{{ asset('img/'.$arquivo.'.png') }}"
    width="{{ $lado }}"
    height="{{ $lado }}"
    alt=""
/>
