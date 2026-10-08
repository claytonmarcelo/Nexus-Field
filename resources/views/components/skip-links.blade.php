{{--
    Links de salto do teclado. A classe .skip-links é a guarda que o AdminLTE
    consulta antes de injetar os dele; entregar os nossos em português evita
    o par duplicado e o texto em inglês.
--}}
@props(['navigation' => false])

<div class="skip-links">
    <a class="skip-link" href="#conteudo">Ir para o conteúdo</a>

    @if ($navigation)
        <a class="skip-link" href="#navigation">Ir para a navegação</a>
    @endif
</div>
