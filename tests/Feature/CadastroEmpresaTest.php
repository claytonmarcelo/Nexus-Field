<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\CompanyBackup;
use App\Models\CompanyRegistration;
use App\Models\User;
use App\Notifications\CadastroAprovado;
use App\Notifications\CadastroAprovadoContaExistente;
use App\Notifications\ConfirmacaoDeCadastro;
use App\Services\Auth\RegistradorDeAcesso;
use App\Services\Cadastro\GestorDeCadastro;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Storage;
use Tests\CreatesFixtures;
use Tests\TestCase;

class CadastroEmpresaTest extends TestCase
{
    use CreatesFixtures, RefreshDatabase;

    private const CNPJ = '11.444.777/0001-61';

    private function administrador(string $email = 'admin@painel.local'): User
    {
        $empresa = $this->makeCompany('empresa-painel');

        return $this->makeUser('administrator', $empresa, $email);
    }

    /** @return array<string, mixed> */
    private function dados(string $email = 'admin@empresa-teste.local'): array
    {
        return [
            'cnpj' => self::CNPJ,
            'razao_social' => 'Empresa Teste Ltda',
            'nome_fantasia' => 'Empresa Teste',
            'admin_nome' => 'Ana Administradora',
            'email' => $email,
            'telefone' => '(11) 99999-0000',
            'uf' => 'SP',
            'cidade' => 'São Paulo',
            'aceite_termos' => '1',
            'aceite_privacidade' => '1',
        ];
    }

    public function test_cadastro_de_empresa_fica_no_painel_e_fora_das_paginas_publicas(): void
    {
        $this->seedPermissions();

        $this->get(route('cadastro.create'))->assertRedirect(route('login'));

        foreach ([route('welcome'), route('login'), route('password.request')] as $url) {
            $this->get($url)->assertDontSee('cadastros/empresas', false);
        }

        $this->actingAs($this->administrador())->get(route('cadastro.create'))
            ->assertOk()
            ->assertSee('Cadastro de empresa')
            ->assertSee('Empresas');
    }

    public function test_cadastro_de_empresa_exige_permissao(): void
    {
        $this->seedPermissions();
        $empresa = $this->makeCompany('empresa-sem-permissao');
        $usuario = $this->makeUser('technician', $empresa, 'tecnico@painel.local');

        $this->actingAs($usuario)->get(route('cadastro.create'))->assertForbidden();
    }

    public function test_inicio_cria_pedido_em_rascunho_e_notifica_confirmacao(): void
    {
        Notification::fake();
        $this->seedPermissions();

        $this->actingAs($this->administrador())->post(route('cadastro.store'), $this->dados())
            ->assertRedirect(route('cadastro.confirmar'))
            ->assertSessionHas('status');

        $registro = CompanyRegistration::firstOrFail();

        $this->assertSame(CompanyRegistration::RASCUNHO, $registro->state);
        $this->assertMatchesRegularExpression('/^NF-CAD-\d{4}-\d{4}$/', $registro->protocol);
        $this->assertSame(hash('sha256', '11444777000161'), $registro->cnpj_hash);
        $this->assertNull($registro->dados['aceite_termos'] ?? null);

        Notification::assertSentOnDemand(ConfirmacaoDeCadastro::class);
    }

    public function test_cnpj_invalido_e_recusado(): void
    {
        $this->seedPermissions();
        $dados = $this->dados();
        $dados['cnpj'] = '11.444.777/0001-62';

        $this->actingAs($this->administrador())->post(route('cadastro.store'), $dados)
            ->assertSessionHasErrors('cnpj');

        $this->assertSame(0, CompanyRegistration::count());
    }

    public function test_cnpj_ja_cadastrado_e_recusado(): void
    {
        $this->seedPermissions();
        $empresa = $this->makeCompany();
        $empresa->forceFill(['cnpj' => '11444777000161'])->save();

        $this->actingAs($this->administrador())->post(route('cadastro.store'), $this->dados())
            ->assertRedirect()
            ->assertSessionHas('erro');

        $this->assertSame(0, CompanyRegistration::count());
    }

    public function test_confirmacao_libera_envio_de_documentos(): void
    {
        $gestor = app(GestorDeCadastro::class);
        $resultado = $gestor->iniciar($this->dados(), '127.0.0.1', 'Test');

        $this->get(route('registration.confirm', [
            'token' => $resultado['email_token'],
            'acesso' => $resultado['access_token'],
        ]))->assertRedirect(route('cadastro.documentos'));

        $this->assertSame(CompanyRegistration::EMAIL_PENDENTE, $resultado['registro']->fresh()->state);
        $this->assertNull($resultado['registro']->fresh()->email_token_hash);
    }

    public function test_link_de_confirmacao_invalido_e_recusado(): void
    {
        $this->get(route('registration.confirm', ['token' => 'invalido']))
            ->assertRedirect()
            ->assertSessionHas('erro');
    }

    public function test_fluxo_completo_ate_aprovacao_cria_empresa_administrador_e_estrutura(): void
    {
        Notification::fake();
        Storage::fake('local');
        $this->seedPermissions();

        $gestor = app(GestorDeCadastro::class);
        $resultado = $gestor->iniciar($this->dados(), '127.0.0.1', 'Test');
        $registro = $resultado['registro'];

        $gestor->confirmarEmail($resultado['email_token'], '127.0.0.1', 'Test');

        $arquivo = UploadedFile::fake()->createWithContent('contrato.pdf', '%PDF-1.4 conteudo de teste');
        $documento = $gestor->anexarDocumento($registro->fresh(), $arquivo, 'contrato_social', '127.0.0.1', 'Test');
        $this->assertSame('local', $documento->disk);
        Storage::disk('local')->assertExists($documento->path);

        $gestor->registrarAceite($registro->fresh(), 'termos_de_uso', '2026-11', '127.0.0.1', 'Test');
        $gestor->registrarAceite($registro->fresh(), 'politica_de_privacidade', '2026-11', '127.0.0.1', 'Test');
        $gestor->submeter($registro->fresh(), '127.0.0.1', 'Test');

        $this->assertSame(CompanyRegistration::EM_ANALISE, $registro->fresh()->state);

        $this->makeCompany('empresa-raiz');
        $raiz = User::create([
            'company_id' => Company::where('slug', 'empresa-raiz')->firstOrFail()->id,
            'name' => 'Raiz',
            'email' => 'raiz@test.local',
            'password' => 'Senha-Forte-123',
            'is_root' => true,
            'status' => 'active',
        ]);
        $raiz->forceFill(['is_root' => true])->save();

        $empresa = $gestor->aprovar($registro->fresh(), $raiz);

        $this->assertSame('Empresa Teste Ltda', $empresa->legal_name);
        $this->assertSame('11444777000161', $empresa->cnpj);
        $this->assertSame(CompanyRegistration::APROVADO, $registro->fresh()->state);
        $this->assertDatabaseHas('users', ['email' => 'admin@empresa-teste.local', 'company_id' => $empresa->id]);
        $this->assertDatabaseHas('departments', ['company_id' => $empresa->id, 'slug' => 'administracao']);
        $this->assertDatabaseHas('roles', ['company_id' => $empresa->id, 'slug' => 'administrator']);
        $this->assertDatabaseHas('company_documents', ['id' => $documento->id, 'company_id' => $empresa->id]);

        // A primeira cópia individual já nasce com a empresa aprovada.
        $backup = CompanyBackup::query()->anyCompany()->where('company_id', $empresa->id)->sole();
        $this->assertSame('configuracao', $backup->tipo);
        Storage::disk('local')->assertExists($backup->path);

        Notification::assertSentOnDemand(CadastroAprovado::class);
    }

    public function test_reprovar_notifica_motivo_e_trilha(): void
    {
        Notification::fake();

        $gestor = app(GestorDeCadastro::class);
        $resultado = $gestor->iniciar($this->dados(), '127.0.0.1', 'Test');
        $registro = $resultado['registro'];
        $gestor->confirmarEmail($resultado['email_token'], '127.0.0.1', 'Test');
        $gestor->anexarDocumento(
            $registro->fresh(),
            UploadedFile::fake()->createWithContent('cnpj.pdf', '%PDF-1.4 teste'),
            'cnpj',
            '127.0.0.1',
            'Test',
        );
        $gestor->registrarAceite($registro->fresh(), 'termos_de_uso', '2026-11', '127.0.0.1', 'Test');
        $gestor->registrarAceite($registro->fresh(), 'politica_de_privacidade', '2026-11', '127.0.0.1', 'Test');
        $gestor->submeter($registro->fresh(), '127.0.0.1', 'Test');

        $empresa = $this->makeCompany();
        $ator = $this->makeUser('administrator', $empresa, 'ator@test.local');

        $gestor->reprovar($registro->fresh(), $ator, 'Documento ilegível.');

        $this->assertSame(CompanyRegistration::REPROVADO, $registro->fresh()->state);
        $this->assertDatabaseHas('company_review_events', [
            'registration_id' => $registro->id,
            'to_state' => 'reprovado',
            'reason' => 'Documento ilegível.',
        ]);
    }

    public function test_aprovacao_gera_token_de_redefinicao_utilizavel(): void
    {
        Notification::fake();
        Storage::fake('local');
        $this->seedPermissions();

        $gestor = app(GestorDeCadastro::class);
        $resultado = $gestor->iniciar($this->dados(), '127.0.0.1', 'Test');
        $registro = $resultado['registro'];
        $gestor->confirmarEmail($resultado['email_token'], '127.0.0.1', 'Test');
        $gestor->anexarDocumento(
            $registro->fresh(),
            UploadedFile::fake()->createWithContent('cnpj.pdf', '%PDF-1.4 teste'),
            'cnpj',
            '127.0.0.1',
            'Test',
        );
        $gestor->registrarAceite($registro->fresh(), 'termos_de_uso', '2026-11', '127.0.0.1', 'Test');
        $gestor->registrarAceite($registro->fresh(), 'politica_de_privacidade', '2026-11', '127.0.0.1', 'Test');
        $gestor->submeter($registro->fresh(), '127.0.0.1', 'Test');

        $empresaRaiz = $this->makeCompany();
        $raiz = $this->makeUser('administrator', $empresaRaiz, 'raiz@test.local');
        $raiz->forceFill(['is_root' => true])->save();

        $empresa = $gestor->aprovar($registro->fresh(), $raiz);

        Notification::assertSentOnDemand(
            CadastroAprovado::class,
            fn (CadastroAprovado $notificacao) => str_contains($notificacao->link, 'reset-password/'),
        );

        $admin = User::where('email', 'admin@empresa-teste.local')->firstOrFail();
        $token = Password::broker()->createToken($admin);

        $this->post(route('password.update'), [
            'token' => $token,
            'email' => $admin->email,
            'password' => 'Nova-Senha-Forte-123',
            'password_confirmation' => 'Nova-Senha-Forte-123',
        ])->assertRedirect(route('login'));

        $this->assertTrue(Hash::check('Nova-Senha-Forte-123', $admin->fresh()->password));
        $this->assertSame($empresa->id, $admin->fresh()->company_id);
    }

    public function test_aprovacao_reaproveita_conta_de_acesso_e_mantem_a_senha(): void
    {
        Notification::fake();
        Storage::fake('local');
        $this->seedPermissions();

        $usuario = app(RegistradorDeAcesso::class)->registrar([
            'name' => 'Marina Cliente',
            'email' => 'marina@exemplo.local',
            'password' => 'Senha-Forte-123',
        ]);
        $empresaInicial = $usuario->company_id;

        $gestor = app(GestorDeCadastro::class);
        $resultado = $gestor->iniciar($this->dados($usuario->email), '127.0.0.1', 'Test', $usuario);
        $registro = $resultado['registro'];

        $gestor->confirmarEmail($resultado['email_token'], '127.0.0.1', 'Test');
        $gestor->anexarDocumento(
            $registro->fresh(),
            UploadedFile::fake()->createWithContent('cnpj.pdf', '%PDF-1.4 teste'),
            'cnpj',
            '127.0.0.1',
            'Test',
        );
        $gestor->registrarAceite($registro->fresh(), 'termos_de_uso', '2026-11', '127.0.0.1', 'Test');
        $gestor->registrarAceite($registro->fresh(), 'politica_de_privacidade', '2026-11', '127.0.0.1', 'Test');
        $gestor->submeter($registro->fresh(), '127.0.0.1', 'Test');

        $raiz = $this->makeUser('administrator', $this->makeCompany('raiz-reuso'), 'raiz-reuso@test.local');
        $raiz->forceFill(['is_root' => true])->save();

        $empresa = $gestor->aprovar($registro->fresh(), $raiz);
        $usuario->refresh();

        $this->assertNotSame($empresaInicial, $usuario->company_id);
        $this->assertSame($empresa->id, $usuario->company_id);
        $this->assertTrue(Hash::check('Senha-Forte-123', $usuario->password));
        $this->assertSame(1, User::where('email', $usuario->email)->count());
        $this->assertTrue($usuario->hasRole('administrator'));

        Notification::assertSentOnDemandOnce(CadastroAprovadoContaExistente::class);
        Notification::assertSentOnDemandTimes(CadastroAprovado::class, 0);
    }
}
