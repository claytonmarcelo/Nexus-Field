<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Support\Auditor;
use App\Support\Settings;
use App\Support\SettingsCatalog;
use App\Support\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * A casa em ordem. Esta é a única tela em que a empresa fala de si mesma: o
 * perfil que o escritório mostra para o time, a tolerância que o check-in aceita
 * e o ritmo com que o sino de todo dia bate. O alcance é o de sempre —
 * `settings.view` abre a tela para leitura, e só `settings.manage`, degrau de
 * administrador, encosta em qualquer valor.
 *
 * Duas coisas não mudam por aqui, e o servidor é quem diz: a chave de endereço
 * da empresa (o slug que já mora em link e log de sistema) e o plano, que é
 * contrato — esta tela apenas explica a cota que ele impõe. O que muda sai com
 * carimbo na auditoria, campo por campo, porque configuração alterada em
 * silêncio é a briga de amanhã sem testemunha.
 */
class SettingController extends Controller
{
    /** Colunas do perfil que esta tela escreve. Slug e plano não estão aqui de propósito. */
    private const CAMPOS_PERFIL = ['name', 'document', 'phone', 'email', 'website'];

    public function index(Request $request): View
    {
        $empresa = $this->empresa();

        return view('settings.index', [
            'empresa' => $empresa,
            'preferencias' => SettingsCatalog::PREFERENCIAS,
            'valores' => $this->valoresTela(),
            'podeGerir' => $request->user()->hasPermission('settings.manage'),
            'contas' => $empresa->users()->whereNull('deleted_at')->count(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validado = $request->validate($this->regras(), [], [], [
            'name' => 'o nome',
            'document' => 'o documento',
            'phone' => 'o telefone',
            'email' => 'o e-mail',
            'website' => 'o site',
            'logo' => 'a marca',
        ]);

        $empresa = $this->empresa();
        $mudancas = $this->gravarPerfil($request, $empresa, $validado);
        $mudancas += $this->gravarPreferencias($request, $validado);

        $total = count($mudancas);

        if ($total === 0) {
            return back()->with('aviso', 'Nada mudou — nem uma vírgula do que estava guardado.');
        }

        Auditor::gravar('configurações alteradas', $empresa, $mudancas,
            $total.' '.($total === 1 ? 'ajuste' : 'ajustes')
            .' em configurações'.($request->hasFile('logo') ? ', com a marca trocada' : '').'.');

        return back()->with('status', $total.' '.($total === 1
            ? 'configuração salva e registrada na auditoria.'
            : 'configurações salvas e registradas na auditoria.'));
    }

    /** @return array<string, mixed> O que a tela mostra: o escolhido quando há escolha, o padrão quando não. */
    private function valoresTela(): array
    {
        $valores = [];

        foreach (array_keys(SettingsCatalog::PREFERENCIAS) as $chave) {
            $valores[$chave] = Settings::valor($chave);
        }

        return $valores;
    }

    /** @return array<string, array<int, string>> */
    private function regras(): array
    {
        $regras = [
            'name' => ['required', 'string', 'max:120'],
            'document' => ['nullable', 'string', 'max:25'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email:rfc', 'max:150', Rule::unique('companies', 'email')
                ->ignore($this->empresa()->id)],
            'website' => ['nullable', 'url:http,https', 'max:150'],
            // SVG fica do lado de fora da casa: é texto, e texto alheio
            // renderizado dentro do layout autenticado é vetor de script. O
            // aceite é bitmap medido — nem retrato de celular nem ícone torto.
            'logo' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:2048',
                'dimensions:min_width=64,min_height=64,max_width=1200,max_height=1200'],
        ];

        foreach (SettingsCatalog::PREFERENCIAS as $chave => $meta) {
            $regras[$chave] = $meta['regras'];
        }

        return $regras;
    }

    /**
     * @param  array<string, mixed>  $validado
     * @return array<string, array{0: mixed, 1: mixed}>
     */
    private function gravarPerfil(Request $request, Company $empresa, array $validado): array
    {
        $mudancas = [];

        foreach (self::CAMPOS_PERFIL as $coluna) {
            $novo = trim((string) ($validado[$coluna] ?? ''));
            $novo = $novo === '' ? null : $novo;

            if ((string) $novo !== (string) $empresa->{$coluna}) {
                $mudancas[$coluna] = [$empresa->{$coluna}, $novo];
            }
        }

        if ($mudancas !== []) {
            $dados = array_intersect_key($validado, array_flip(self::CAMPOS_PERFIL));
            $dados['name'] = trim($dados['name']);
            $empresa->newQuery()->withoutGlobalScopes()->whereKey($empresa->id)->update($dados);
        }

        $logo = $request->file('logo');

        if ($logo instanceof UploadedFile) {
            $mudancas['logo'] = [$empresa->logo_path === null ? 'a marca padrão' : 'a marca anterior', 'a marca enviada agora'];
            $this->guardarLogo($empresa, $logo);
        }

        return $mudancas;
    }

    /** @return array<string, array{0: mixed, 1: mixed}> */
    private function gravarPreferencias(Request $request, array $validado): array
    {
        $mudancas = [];
        $escolhidas = [];

        foreach (SettingsCatalog::PREFERENCIAS as $chave => $meta) {
            $antes = Settings::valor($chave);
            $depois = is_bool($meta['padrao'])
                ? $request->boolean($chave)
                : $this->numero((string) ($validado[$chave] ?? ''), $meta['padrao']);

            if ($depois !== $antes) {
                $mudancas[$chave] = [$antes, $depois];
            }

            // Guardar igual ao padrão seria opinião duplicada: a linha volta a
            // ficar de fora, e a tela passa a mostrar o padrão da casa de novo.
            $escolhidas[$chave] = $depois === $meta['padrao'] ? null : $depois;
        }

        Settings::gravar($escolhidas);

        return $mudancas;
    }

    /** Campo de número vazio é "voltar ao padrão da casa" — ninguém é obrigado a opinar sobre tudo. */
    private function numero(string $bruto, mixed $padrao): mixed
    {
        return $bruto === '' ? $padrao : (int) $bruto;
    }

    /**
     * A marca da empresa no disco público. O arquivo antigo sai junto com a
     * troca — imagem órfã em storage não é histórico, é entulho. O caminho é
     * sempre gerado por nós (nunca o nome enviado), dentro da pasta da empresa.
     */
    private function guardarLogo(Company $empresa, UploadedFile $arquivo): void
    {
        $antigo = $empresa->logo_path;

        $extensao = Str::lower($arquivo->extension() ?: 'png');
        $caminho = "empresas/{$empresa->id}/logo-".Str::random(20).".{$extensao}";

        Storage::disk('public')->put($caminho, file_get_contents($arquivo->getRealPath()));
        $empresa->newQuery()->withoutGlobalScopes()->whereKey($empresa->id)
            ->update(['logo_path' => 'storage/'.$caminho]);

        if (filled($antigo) && str_starts_with($antigo, 'storage/empresas/')) {
            Storage::disk('public')->delete(mb_substr($antigo, strlen('storage/')));
        }
    }

    private function empresa(): Company
    {
        $empresa = Company::query()->find(TenantContext::id());

        abort_unless($empresa !== null, 404, 'Esta conta não pertence a uma empresa com perfil para configurar.');

        return $empresa;
    }
}
