<x-cadastro-shell
    title="Documentos do cadastro"
    subtitle="Envie os documentos que serão analisados pela plataforma. Arquivos privados, nunca publicados."
    :step="3"
>
    <p class="nf-cadastro-protocolo">
        Protocolo: <strong>{{ $registro->protocol }}</strong>
    </p>

    @if ($documentos->isNotEmpty())
        <ul class="nf-cadastro-docs">
            @foreach ($documentos as $documento)
                <li>
                    <i class="fa-solid fa-file-circle-check" aria-hidden="true"></i>
                    <span>{{ $documento->original_name }}</span>
                    <small>{{ str_replace('_', ' ', $documento->type) }}</small>
                </li>
            @endforeach
        </ul>
    @endif

    <form method="POST" action="{{ route('cadastro.documentos.anexar') }}" enctype="multipart/form-data" data-nf-guard novalidate>
        @csrf

        <div class="row g-3">
            <div class="col-md-5">
                <label class="form-label" for="tipo">Tipo de documento</label>
                <select class="form-select disable-adminlte-validations" id="tipo" name="tipo" required>
                    @foreach (\App\Models\CompanyDocument::TIPOS as $tipo)
                        <option value="{{ $tipo }}" @selected(old('tipo') === $tipo)>{{ str_replace('_', ' ', ucfirst($tipo)) }}</option>
                    @endforeach
                </select>
                @error('tipo') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
            </div>
            <div class="col-md-7">
                <x-ui.input label="Arquivo (PDF, PNG ou JPG — até 5 MB)" name="arquivo" type="file" required />
            </div>
        </div>

        <x-ui.button type="submit" variant="secondary" size="lg" wide icon="fa-solid fa-upload" class="mt-3">
            Enviar documento
        </x-ui.button>
    </form>

    <h2 class="nf-cadastro-grupo mt-4">Aceites legais</h2>

    @foreach ([\App\Models\LegalAcceptance::TERMOS => 'Termos de uso', \App\Models\LegalAcceptance::PRIVACIDADE => 'Política de privacidade'] as $kind => $rotulo)
        <form method="POST" action="{{ route('cadastro.aceitar') }}" class="nf-cadastro-aceite">
            @csrf
            <input type="hidden" name="kind" value="{{ $kind }}">
            <input type="hidden" name="versao" value="2026-11">
            <x-ui.button type="submit" variant="secondary" size="sm" icon="fa-solid fa-check">
                Aceitar {{ $rotulo }}
            </x-ui.button>
        </form>
    @endforeach

    @if ($registro->acceptances()->count() >= 2 && $documentos->isNotEmpty())
        <form method="POST" action="{{ route('cadastro.enviar') }}" class="mt-4">
            @csrf
            <x-ui.button type="submit" variant="primary" size="lg" wide icon="fa-solid fa-paper-plane">
                Enviar cadastro para análise
            </x-ui.button>
        </form>
    @else
        <p class="nf-cadastro-dica">
            Envie ao menos um documento e aceite os dois termos para liberar o envio.
        </p>
    @endif
</x-cadastro-shell>
