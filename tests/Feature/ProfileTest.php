<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\Technician;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Tests\CreatesFixtures;
use Tests\TestCase;

/**
 * O espelho que faltava na cadeia do prompt-mestre (welcome → acessar → login →
 * dashboard → módulos → PERFIL → logout). As provas abaixo não medem se a tela
 * abriu: medem a fronteira. Perfil não tem permissão nenhuma no caminho — é a
 * única coisa que pertence a quem está logado —, e justamente por isso a chave de
 * acesso e a senha só se trocam com a senha atual na mesa. Sem essa prova, um
 * terminal deixado aberto seria a porta para assumir a identidade alheia.
 *
 * A recusa também é teste: em Laravel, `back()->with('erro', ...)` escreve o flash
 * antes do return, então `assertSessionHas` sozinho passaria até num 500. Toda
 * recusa abaixo carimba o 302 junto.
 */
class ProfileTest extends TestCase
{
    use CreatesFixtures, RefreshDatabase;

    private const SENHA = 'Senha-Forte-123';

    private const SENHA_NOVA = 'Outra-Senha-Forte-2026';

    public function test_o_espelho_abre_para_quem_esta_logado_sem_pedir_permissao(): void
    {
        $this->seedPermissions();
        $empresa = $this->makeCompany('alfa');
        $tecnica = $this->makeUser('technician', $empresa, 'tecnica@test.local');

        $this->get(route('profile.show'))->assertRedirect(route('login'));

        $this->actingAs($tecnica)
            ->get(route('profile.show'))
            ->assertOk()
            ->assertSee('Meu perfil')
            ->assertSee('tecnica@test.local')
            ->assertSee('Chave de acesso');
    }

    public function test_mudar_o_proprio_nome_grava_e_deixa_o_carimbo_na_auditoria(): void
    {
        $this->seedPermissions();
        $empresa = $this->makeCompany('alfa');
        $conta = $this->makeUser('employee', $empresa, 'geo@test.local');

        $this->actingAs($conta)
            ->put(route('profile.update'), [
                'name' => 'Geovana Andrade',
                'phone' => '(11) 98888-7777',
            ])
            ->assertStatus(302)
            ->assertRedirect(route('profile.show'));

        $atualizada = $conta->fresh();
        $this->assertSame('Geovana Andrade', $atualizada->name);
        $this->assertSame('(11) 98888-7777', $atualizada->phone);

        $ato = AuditLog::query()->where('action', 'perfil atualizado')->sole();
        $this->assertStringContainsString('Geovana', json_encode($ato->changes, JSON_UNESCAPED_UNICODE));
    }

    public function test_salvar_tudo_igual_nao_inventa_carimbo_na_trilha(): void
    {
        $this->seedPermissions();
        $empresa = $this->makeCompany('alfa');
        $conta = $this->makeUser('employee', $empresa, 'geo@test.local');

        $this->actingAs($conta)
            ->put(route('profile.update'), ['name' => $conta->name, 'phone' => $conta->phone])
            ->assertStatus(302)
            ->assertSessionHas('info');

        $this->assertSame(0, AuditLog::query()->where('action', 'perfil atualizado')->count());
    }

    public function test_a_chave_nao_se_troca_sem_a_senha_atual_nem_com_e_mail_de_outrem(): void
    {
        $this->seedPermissions();
        $empresa = $this->makeCompany('alfa');
        $conta = $this->makeUser('employee', $empresa, 'geo@test.local');
        $this->makeUser('supervisor', $empresa, 'outra@test.local');

        // Senha atual errada: a chave fica onde está.
        $this->actingAs($conta)
            ->put(route('profile.email'), ['email' => 'novo@test.local', 'senha_atual' => 'senha-torta-123'])
            ->assertStatus(302)
            ->assertSessionHasErrors('senha_atual');

        $this->assertSame('geo@test.local', $conta->fresh()->email);

        // E-mail que já é chave de outra conta: recusado antes de qualquer gravação.
        $this->actingAs($conta)
            ->put(route('profile.email'), ['email' => 'outra@test.local', 'senha_atual' => self::SENHA])
            ->assertStatus(302)
            ->assertSessionHasErrors('email');

        $this->assertSame('geo@test.local', $conta->fresh()->email);
    }

    public function test_trocar_a_chave_com_a_senha_atual_passa_a_valer_no_login(): void
    {
        $this->seedPermissions();
        $empresa = $this->makeCompany('alfa');
        $conta = $this->makeUser('employee', $empresa, 'geo@test.local');

        $this->actingAs($conta)
            ->put(route('profile.email'), ['email' => ' NOVO@Test.local ', 'senha_atual' => self::SENHA])
            ->assertStatus(302)
            ->assertSessionHas('status');

        $this->assertSame('novo@test.local', $conta->fresh()->email);
        $this->assertSame(1, AuditLog::query()->where('action', 'chave de acesso alterada')->count());

        $this->post(route('logout'))->assertStatus(302);

        $this->post(route('login'), ['email' => 'novo@test.local', 'password' => self::SENHA])
            ->assertRedirect(route('dashboard'));
        $this->assertAuthenticated();

        $this->post(route('logout'));
        $this->post(route('login'), ['email' => 'geo@test.local', 'password' => self::SENHA])
            ->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_a_conta_raiz_se_mira_no_espelho_mas_nao_troca_a_propria_chave(): void
    {
        $this->seedPermissions();
        $empresa = $this->makeCompany('alfa');
        $raiz = $this->makeUser('administrator', $empresa, 'raiz@test.local');
        $raiz->forceFill(['is_root' => true])->save();

        $this->actingAs($raiz)
            ->get(route('profile.show'))
            ->assertOk()
            ->assertSee('A raiz não troca de chave');

        $this->actingAs($raiz)
            ->put(route('profile.email'), ['email' => 'outracoisa@test.local', 'senha_atual' => self::SENHA])
            ->assertStatus(302)
            ->assertSessionHas('erro');

        $this->assertSame('raiz@test.local', $raiz->fresh()->email);
    }

    public function test_renovar_a_senha_apaga_a_antiga_e_nao_deixa_a_nova_em_log_nem_de_sessao(): void
    {
        $this->seedPermissions();
        $empresa = $this->makeCompany('alfa');
        $conta = $this->makeUser('employee', $empresa, 'geo@test.local');

        $this->actingAs($conta)
            ->put(route('profile.password'), [
                'senha_atual' => 'senha-torta-123',
                'password' => self::SENHA_NOVA,
                'password_confirmation' => self::SENHA_NOVA,
            ])
            ->assertStatus(302)
            ->assertSessionHasErrors('senha_atual');

        $this->assertTrue(Hash::check(self::SENHA, $conta->fresh()->password));

        $this->actingAs($conta)
            ->put(route('profile.password'), [
                'senha_atual' => self::SENHA,
                'password' => self::SENHA_NOVA,
                'password_confirmation' => self::SENHA_NOVA,
            ])
            ->assertStatus(302)
            ->assertSessionHas('status');

        $renovada = $conta->fresh();
        $this->assertTrue(Hash::check(self::SENHA_NOVA, $renovada->password));
        $this->assertFalse(Hash::check(self::SENHA, $renovada->password));

        // A sessão que renovou continua viva; a senha velha não abre mais a porta.
        $this->actingAs($renovada)->get(route('profile.show'))->assertOk();
        $this->assertFalse(Auth::attempt(['email' => 'geo@test.local', 'password' => self::SENHA]));

        $ato = AuditLog::query()->where('action', 'senha alterada')->sole();
        $this->assertStringNotContainsString(self::SENHA_NOVA, json_encode($ato->toArray(), JSON_UNESCAPED_UNICODE));
    }

    public function test_a_senha_nova_precisa_ser_distinta_e_forte_antes_de_gravar_qualquer_coisa(): void
    {
        $this->seedPermissions();
        $empresa = $this->makeCompany('alfa');
        $conta = $this->makeUser('employee', $empresa, 'geo@test.local');

        $this->actingAs($conta)
            ->put(route('profile.password'), [
                'senha_atual' => self::SENHA,
                'password' => self::SENHA,
                'password_confirmation' => self::SENHA,
            ])
            ->assertStatus(302)
            ->assertSessionHasErrors('password');

        $this->actingAs($conta)
            ->put(route('profile.password'), [
                'senha_atual' => self::SENHA,
                'password' => 'fraca',
                'password_confirmation' => 'fraca',
            ])
            ->assertStatus(302)
            ->assertSessionHasErrors('password');

        $this->assertTrue(Hash::check(self::SENHA, $conta->fresh()->password));
        $this->assertSame(0, AuditLog::query()->where('action', 'senha alterada')->count());
    }

    public function test_o_perfil_do_tecnico_mostra_a_janela_dele_e_nao_a_de_outro_campo(): void
    {
        $this->seedPermissions();
        $empresa = $this->makeCompany('alfa');
        $conta = $this->makeUser('technician', $empresa, 'tecnica@test.local');

        $minha = Technician::query()->create([
            'company_id' => $empresa->id,
            'user_id' => $conta->id,
            'name' => 'Técnica Minha',
            'status' => 'available',
        ]);

        $alheia = Technician::query()->create([
            'company_id' => $empresa->id,
            'name' => 'Técnico Vizinho',
            'status' => 'available',
        ]);

        $this->janela($empresa, $minha, 'Visita no bairro certo');
        $this->janela($empresa, $alheia, 'Visita do outro técnico');

        $html = $this->actingAs($conta)->get(route('profile.show'))->assertOk()->getContent();

        $this->assertStringContainsString('Visita no bairro certo', $html);
        $this->assertStringNotContainsString('Visita do outro técnico', $html);
        $this->assertStringContainsString('Suas próximas janelas', $html);
    }

    public function test_o_dropdown_do_cabecalho_leva_ao_espelho(): void
    {
        $this->seedPermissions();
        $empresa = $this->makeCompany('alfa');
        $conta = $this->makeUser('employee', $empresa, 'geo@test.local');

        $this->actingAs($conta)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Meu perfil')
            ->assertSee(route('profile.show'), false);
    }

    private function janela(Company $empresa, Technician $tecnico, string $titulo): Appointment
    {
        return Appointment::query()->create([
            'company_id' => $empresa->id,
            'technician_id' => $tecnico->id,
            'title' => $titulo,
            'type' => 'custom',
            'status' => 'scheduled',
            'starts_at' => now()->addDay()->setTime(9, 0),
            'ends_at' => now()->addDay()->setTime(10, 0),
            'all_day' => false,
        ]);
    }
}
