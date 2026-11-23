{{-- Cabeça comum às telas abertas: fontes, tema antes da pintura e assets do Vite. --}}
@props(['title', 'description' => null, 'leve' => false])

<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">

@if ($description)
    <meta name="description" content="{{ $description }}">
@endif

<title>{{ $title }}</title>

{{-- Ícones do navegador: o PNG manda nos modernos, o .ico cobre os que só
     pedem a raiz, e o apple-touch-icon é chato sobre fundo porque a Maçã não
     aceita transparência. A cor da barra vem do x-script.theme, por tema. --}}
<link rel="icon" type="image/png" sizes="32x32" href="{{ asset('img/marca-nexus-32.png') }}">
<link rel="icon" type="image/png" sizes="192x192" href="{{ asset('img/marca-nexus-192.png') }}">
<link rel="icon" href="{{ asset('favicon.ico') }}">
<link rel="apple-touch-icon" href="{{ asset('img/apple-touch-icon.png') }}">

@fonts
<x-script.theme />
{{-- Telas abertas de acesso não pagam pelo bundle da aplicação: AdminLTE,
     Summernote, Leaflet e Toastr não existem num formulário de entrada. --}}
@vite($leve
    ? ['resources/css/auth.css', 'resources/js/auth.js']
    : ['resources/css/app.css', 'resources/js/app.js'])
