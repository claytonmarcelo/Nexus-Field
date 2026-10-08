{{-- Cabeça comum às telas abertas: fontes, tema antes da pintura e assets do Vite. --}}
@props(['title', 'description' => null])

<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">

@if ($description)
    <meta name="description" content="{{ $description }}">
@endif

<title>{{ $title }}</title>

@fonts
<x-script.theme />
@vite(['resources/css/app.css', 'resources/js/app.js'])
