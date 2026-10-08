<?php

namespace Database\Seeders;

use App\Models\Appointment;
use App\Models\Client;
use App\Models\Company;
use App\Models\FinancialRecord;
use App\Models\Notification;
use App\Models\Payment;
use App\Models\Permission;
use App\Models\Plan;
use App\Models\Product;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\ServiceOrder;
use App\Models\ServiceOrderAssignment;
use App\Models\ServiceOrderCheckin;
use App\Models\ServiceOrderStatusHistory;
use App\Models\Specialty;
use App\Models\StockMovement;
use App\Models\Team;
use App\Models\Technician;
use App\Models\Ticket;
use App\Models\TicketComment;
use App\Models\User;
use App\Support\PermissionCatalog;
use App\Support\Roles;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Dados de demonstração do painel, SEMPRE em uma empresa à parte
 * ("Nexus-Field Demonstração"): nada aqui entra na empresa real, e o seeder recusa
 * produção. Rode sozinho, nunca pelo DatabaseSeeder:
 *
 *   php artisan db:seed --class=DemoSeeder
 *
 * As datas são relativas ao dia em que o seeder roda, para o painel ter hoje,
 * ontem e semana em andamento. Rodar de novo limpa a empresa de demonstração e
 * recria do zero — é fixture de tela, não histórico de operação.
 */
class DemoSeeder extends Seeder
{
    public const COMPANY_SLUG = 'nexusfield-demo';

    public const COMPANY_NAME = 'Nexus-Field Demonstração';

    private Company $empresa;

    /** @var array<string, User> */
    private array $usuarios = [];

    /** @var array<string, int> slug de permissão => id */
    private array $permissions = [];

    /** @var array<int, Specialty> */
    private array $especialidades = [];

    /** @var array<int, Client> */
    private array $clientes = [];

    /** @var array<int, Technician> */
    private array $tecnicos = [];

    /** @var array<int, Service> */
    private array $servicos = [];

    /** @var array<int, Product> */
    private array $produtos = [];

    /** @var array<string, ServiceOrder> */
    private array $ordens = [];

    public function run(): void
    {
        if (app()->environment('production')) {
            throw new \RuntimeException(
                'DemoSeeder não roda em produção: os dados daqui são fixture de tela e se '.
                'misturariam com a operação real.'
            );
        }

        DB::transaction(function () {
            $this->empresa();
            $this->limpar();
            $this->usuarios();
            $this->clientes();
            $this->catalogo();
            $this->equipe();
            $this->ordens();
            $this->estoque();
            $this->chamados();
            $this->agenda();
            $this->financeiro();
            $this->notificacoes();
        });

        $this->command?->info('Demonstração: empresa "'.self::COMPANY_SLUG.'" pronta com '.$this->resumo().'.');
    }

    private function empresa(): void
    {
        $plan = Plan::query()->firstOrCreate(
            ['slug' => 'operacao'],
            [
                'name' => 'Operação',
                'max_users' => 25,
                'max_technicians' => 25,
                'max_clients' => 2000,
                'max_service_orders_per_month' => 5000,
                'price_monthly' => 0,
                'features' => ['fsm', 'agenda', 'relatorios'],
                'is_active' => true,
            ],
        );

        $this->empresa = Company::query()->firstOrCreate(
            ['slug' => self::COMPANY_SLUG],
            [
                'name' => self::COMPANY_NAME,
                'document' => '41.020.556/0001-33',
                'plan_id' => $plan->id,
                'email' => 'demonstracao@nexusfield.local',
                'phone' => '(11) 4000-0000',
                'subscription_status' => 'active',
                'status' => 'active',
            ],
        );

        $permissions = Permission::query()->pluck('id', 'slug')->all();

        if ($permissions === []) {
            throw new \RuntimeException('Rode DatabaseSeeder antes: o catálogo de permissões está vazio.');
        }

        $this->permissions = $permissions;
    }

    private function limpar(): void
    {
        $empresa = $this->empresa->id;

        $ordens = DB::table('service_orders')->where('company_id', $empresa)->pluck('id')->all();
        $clientes = DB::table('clients')->where('company_id', $empresa)->pluck('id')->all();
        $tecnicos = DB::table('technicians')->where('company_id', $empresa)->pluck('id')->all();
        $chamados = DB::table('tickets')->where('company_id', $empresa)->pluck('id')->all();
        $lancamentos = DB::table('financial_records')->where('company_id', $empresa)->pluck('id')->all();

        if ($ordens !== []) {
            DB::table('service_order_items')->whereIn('service_order_id', $ordens)->delete();
            DB::table('service_order_assignments')->whereIn('service_order_id', $ordens)->delete();
            DB::table('service_order_status_history')->whereIn('service_order_id', $ordens)->delete();
            DB::table('service_order_checkins')->whereIn('service_order_id', $ordens)->delete();
            DB::table('service_orders')->where('company_id', $empresa)->delete();
            DB::table('appointments')->whereIn('service_order_id', $ordens)->delete();
        }

        if ($chamados !== []) {
            DB::table('ticket_comments')->whereIn('ticket_id', $chamados)->delete();
            DB::table('tickets')->where('company_id', $empresa)->delete();
        }

        if ($lancamentos !== []) {
            DB::table('payments')->whereIn('financial_record_id', $lancamentos)->delete();
            DB::table('financial_records')->where('company_id', $empresa)->delete();
        }

        if ($clientes !== []) {
            DB::table('client_contacts')->whereIn('client_id', $clientes)->delete();
        }

        if ($tecnicos !== []) {
            DB::table('team_members')->whereIn('technician_id', $tecnicos)->delete();
            DB::table('specialty_technician')->whereIn('technician_id', $tecnicos)->delete();
        }

        DB::table('appointments')->where('company_id', $empresa)->delete();
        DB::table('attachments')->where('company_id', $empresa)->delete();
        DB::table('notifications')->where('company_id', $empresa)->delete();
        DB::table('stock_movements')->where('company_id', $empresa)->delete();
        DB::table('technician_stocks')->where('company_id', $empresa)->delete();
        DB::table('payments')->where('company_id', $empresa)->delete();
        DB::table('financial_records')->where('company_id', $empresa)->delete();
        DB::table('teams')->where('company_id', $empresa)->delete();
        DB::table('technicians')->where('company_id', $empresa)->delete();
        DB::table('specialties')->where('company_id', $empresa)->delete();
        DB::table('products')->where('company_id', $empresa)->delete();
        DB::table('services')->where('company_id', $empresa)->delete();
        DB::table('service_categories')->where('company_id', $empresa)->delete();
        DB::table('addresses')->where('company_id', $empresa)->delete();
        DB::table('clients')->where('company_id', $empresa)->delete();
        DB::table('company_settings')->where('company_id', $empresa)->delete();

        $ids = DB::table('users')->where('company_id', $empresa)->pluck('id')->all();

        if ($ids !== []) {
            DB::table('role_user')->whereIn('user_id', $ids)->delete();
            DB::table('users')->where('company_id', $empresa)->delete();
        }

        DB::table('roles')->where('company_id', $empresa)->delete();
    }

    private function usuarios(): void
    {
        // Sem credencial no código: a senha vem do ambiente. Sem SEED_DEMO_PASSWORD,
        // reaproveita a do administrador semeado (mesmo uso local); sem nenhuma,
        // gera e mostra no console.
        $senha = env('SEED_DEMO_PASSWORD') ?: env('SEED_ADMIN_PASSWORD');

        if (! $senha) {
            $senha = Str::password(16);
            $this->command?->warn('Senha dos usuários de demonstração: '.$senha);
        }

        $papeis = Roles::provision($this->empresa, $this->permissions);

        foreach ([
            ['Administrador (demo)', 'admin.demo@nexusfield.local', 'administrator'],
            ['Supervisora (demo)', 'gestor.demo@nexusfield.local', 'supervisor'],
            ['Técnico (demo)', 'campo.demo@nexusfield.local', 'technician'],
            ['Cliente (demo)', 'cliente.demo@nexusfield.local', 'client'],
        ] as [$nome, $email, $papel]) {
            $usuario = User::query()->firstOrNew(['email' => $email]);
            $usuario->company_id = $this->empresa->id;
            $usuario->name = $nome;
            $usuario->password = $senha;
            $usuario->status = 'active';
            $usuario->email_verified_at = now();
            $usuario->last_login_at = now()->subHours(2);
            $usuario->save();

            $usuario->roles()->syncWithoutDetaching([$papeis[$papel]->id]);
            $this->usuarios[$papel] = $usuario;
        }
    }

    private function clientes(): void
    {
        foreach ([
            ['Padaria Sant\'Anna', 'Sant\'Anna', '21.402.388/0001-81', 'contato@santanna.com.br', '(11) 3222-1010', 'Rua Aurora', '210', 'Centro', -23.5475, -46.6361, 'active', '01011000'],
            ['Restaurante Vinha d\'Uva', 'Vinha d\'Uva', '22.391.075/0001-44', 'geral@vinhaduva.com.br', '(11) 3555-2020', 'Alameda Sarut', '120', 'Jardins', -23.5708, -46.6642, 'active', '01452001'],
            ['Café Bica Quente', 'Bica Quente', '23.812.440/0001-19', 'oi@bicaquente.com.br', '(11) 2666-3030', 'Rua das Palmeiras', '45', 'Santa Cecília', -23.5431, -46.6523, 'active', '01230001'],
            ['Empório Serra Verde', 'Serra Verde', '24.553.118/0001-72', 'compras@serraverde.com.br', '(11) 2888-4040', 'Avenida Angélica', '980', 'Higienópolis', -23.5461, -46.6557, 'active', '01227000'],
            ['Gelato Bella Manteiga', 'Bella Manteiga', '25.908.331/0001-05', 'loja@bellamanteiga.com.br', '(11) 3099-5050', 'Rua Teodoro Sertório', '302', 'Pinheiros', -23.5668, -46.6841, 'active', '05403000'],
            ['Doceria Amêndoa Doce', 'Amêndoa Doce', '26.317.902/0001-63', 'contato@amendoadoce.com.br', '(11) 2444-6060', 'Rua Voluntários da Pátria', '77', 'Tatuapé', -23.5342, -46.5764, 'active', '03023001'],
            ['Pizzaria Forno Alto', 'Forno Alto', '27.440.126/0001-38', 'pedidos@fornoalto.com.br', '(11) 2777-7070', 'Avenida Santo Amaro', '1400', 'Brooklin', -23.6112, -46.6901, 'active', '04556000'],
            ['Mercearia Dom Tomate', 'Dom Tomate', '28.119.604/0001-91', 'domtomate@outlook.com.br', '(11) 2555-8080', 'Rua Maria Anta', '512', 'Ipiranga', -23.6042, -46.6021, 'active', '04207000'],
            ['Cervejaria Lúpulo Bom', 'Lúpulo Bom', '29.702.115/0001-27', 'bar@lupulobom.com.br', '(11) 2333-9090', 'Rua Aspicuelta', '88', 'Vila Madalena', -23.5581, -46.6913, 'active', '05433001'],
            ['Restaurante Rancho do Peixe', 'Rancho do Peixe', '30.285.771/0001-10', 'contato@ranchodopeixe.com.br', '(11) 3999-1212', 'Avenida do Estado', '3200', 'Ipiranga', -23.5931, -46.6118, 'inactive', '04282000'],
        ] as [$nome, $fantasia, $documento, $email, $telefone, $rua, $numero, $bairro, $latitude, $longitude, $status, $cep]) {
            $cliente = Client::query()->firstOrCreate(
                ['company_id' => $this->empresa->id, 'document' => $documento],
                [
                    'name' => $nome,
                    'trade_name' => $fantasia,
                    'email' => $email,
                    'phone' => $telefone,
                    'status' => $status,
                    'notes' => $status === 'active'
                        ? 'Contrato de manutenção preventiva em vigor.'
                        : 'Contrato pausado a pedido do cliente.',
                ],
            );

            $cliente->addresses()->create([
                'company_id' => $this->empresa->id,
                'type' => 'service',
                'street' => $rua,
                'number' => $numero,
                'neighborhood' => $bairro,
                'city' => 'São Paulo',
                'state' => 'SP',
                'zip_code' => $cep,
                'latitude' => $latitude,
                'longitude' => $longitude,
                'is_primary' => true,
            ]);

            $cliente->contacts()->create([
                'name' => 'Gerência da unidade',
                'email' => $email,
                'phone' => $telefone,
                'role' => 'Gerente',
                'is_primary' => true,
            ]);

            $this->clientes[] = $cliente;
        }

        // A conta "Cliente (demo)" precisa de uma carteira, senão o papel de
        // cliente não teria o que abrir e a listagem de ordens sairia vazia para
        // ele. Ela entra ligada ao primeiro cliente semeado aqui.
        $this->usuarios['client']->update(['client_id' => $this->clientes[0]->id]);
    }

    private function catalogo(): void
    {
        $categorias = [];

        foreach (['Refrigeração', 'Confeitaria profissional', 'Cozinha e cocção', 'Café e bebidas'] as $nome) {
            $categorias[$nome] = ServiceCategory::query()->firstOrCreate([
                'company_id' => $this->empresa->id,
                'slug' => Str::slug($nome),
            ], ['name' => $nome]);
        }

        foreach ([
            ['CL-01', 'Manutenção preventiva de forno combinado', 'Refrigeração', 690.00, 120],
            ['CL-02', 'Higienização de câmara fria', 'Refrigeração', 480.00, 90],
            ['CL-03', 'Instalação de vitrine refrigerada', 'Refrigeração', 1250.00, 240],
            ['SB-01', 'Revisão de batedeira planetária', 'Confeitaria profissional', 320.00, 60],
            ['SB-02', 'Troca de correias e lubrificação de soveladeira', 'Confeitaria profissional', 410.00, 75],
            ['CZ-01', 'Manutenção de fogão profissional 6 bocas', 'Cozinha e cocção', 780.00, 150],
            ['CZ-02', 'Limpeza técnica de coifa e dutos', 'Cozinha e cocção', 950.00, 180],
            ['CF-01', 'Descalcificação e calibração de máquina de espresso', 'Café e bebidas', 540.00, 90],
            ['CF-02', 'Troca de resistência de aquecedor de xícara', 'Café e bebidas', 260.00, 45],
        ] as [$codigo, $nome, $categoria, $preco, $minutos]) {
            $servico = Service::query()->firstOrCreate(
                ['company_id' => $this->empresa->id, 'name' => $nome],
                [
                    'code' => $codigo,
                    'service_category_id' => $categorias[$categoria]->id,
                    'description' => 'Executado por técnico habilitado, com relatório de campo e teste de funcionamento.',
                    'price' => $preco,
                    'estimated_minutes' => $minutos,
                    'status' => 'active',
                ],
            );

            $this->servicos[$codigo] = $servico;
        }

        foreach ([
            ['RF-100', 'Gás refrigerante R-134a (botijão 1 kg)', 'un', 96.00, 168.00, 6],
            ['RF-101', 'Termostato digital para câmara fria', 'un', 118.00, 214.00, 4],
            ['EL-200', 'Resistência de aquecimento 2.200 W', 'un', 74.50, 149.00, 8],
            ['EL-201', 'Sensor de temperatura PT100', 'un', 42.00, 89.90, 10],
            ['VC-300', 'Correia dentada para batedeira', 'un', 28.00, 62.00, 12],
            ['HY-400', 'Filtro depurador de coifa (caixa com 4)', 'cx', 155.00, 289.00, 3],
            ['CF-500', 'Vedações de grupo de espresso (jogo)', 'jg', 63.00, 128.00, 5],
            ['GE-600', 'Lubrificante alimentício 500 ml', 'un', 58.00, 112.00, 4],
        ] as [$sku, $nome, $unidade, $custo, $preco, $reposicao]) {
            $this->produtos[] = Product::query()->firstOrCreate(
                ['company_id' => $this->empresa->id, 'sku' => $sku],
                [
                    'name' => $nome,
                    'description' => 'Peça de linha usada nas manutenções do catálogo.',
                    'unit' => $unidade,
                    'cost' => $custo,
                    'price' => $preco,
                    'reorder_point' => $reposicao,
                    'status' => 'active',
                ],
            );
        }
    }

    private function equipe(): void
    {
        foreach (['Refrigeração comercial', 'Cozinha profissional', 'Confeitaria', 'Café e bebidas'] as $nome) {
            $this->especialidades[] = Specialty::query()->firstOrCreate([
                'company_id' => $this->empresa->id,
                'slug' => Str::slug($nome),
            ], ['name' => $nome]);
        }

        foreach ([
            ['Otávio Bastos', '12.501.330-01', '(11) 98800-1101', 'otavio.bastos@nexusfield.local', 'available', 'Zona Oeste', -23.5581, -46.6913, 0],
            ['Marina Prado', '12.501.330-02', '(11) 98800-1102', 'marina.prado@nexusfield.local', 'busy', 'Centro', -23.5475, -46.6361, 1],
            ['Cléber Ramos', '12.501.330-03', '(11) 98800-1103', 'cleber.ramos@nexusfield.local', 'busy', 'Zona Sul', -23.6112, -46.6901, 2],
            ['Sueli Ferraz', '12.501.330-04', '(11) 98800-1104', 'sueli.ferraz@nexusfield.local', 'available', 'Zona Norte', -23.5342, -46.5764, 3],
            ['Ivan Coutinho', '12.501.330-05', '(11) 98800-1105', 'ivan.coutinho@nexusfield.local', 'off', 'Leste', -23.6042, -46.6021, null],
            ['Renata Val', '12.501.330-06', '(11) 98800-1106', 'renata.val@nexusfield.local', 'available', 'Centro', -23.5431, -46.6523, null],
            ['Pedro Arcanjo', '12.501.330-07', '(11) 98800-1107', 'pedro.arcanjo@nexusfield.local', 'inactive', 'Zona Sul', null, null, null],
        ] as $indice => [$nome, $documento, $telefone, $email, $status, $regiao, $latitude, $longitude, $especialidade]) {
            $tecnico = Technician::query()->firstOrCreate(
                ['company_id' => $this->empresa->id, 'document' => $documento],
                [
                    'user_id' => $indice === 2 ? $this->usuarios['technician']->id : null,
                    'name' => $nome,
                    'email' => $email,
                    'phone' => $telefone,
                    'status' => $status,
                    'region' => $regiao,
                    'latitude' => $latitude,
                    'longitude' => $longitude,
                    'last_location_at' => $latitude === null ? null : now()->subMinutes(18 * ($indice + 1)),
                    'admission_date' => now()->subYears(3)->addDays(30 * $indice)->toDateString(),
                    'notes' => $status === 'inactive' ? 'Desligado — cadastro preservado pelo histórico de ordens.' : null,
                ],
            );

            if ($especialidade !== null) {
                $tecnico->specialties()->syncWithoutDetaching([$this->especialidades[$especialidade]->id]);
            }

            $this->tecnicos[] = $tecnico;
        }

        foreach ([
            ['Equipe Centro', 1, 'Centro'],
            ['Equipe Zona Sul', 2, 'Zona Sul'],
            ['Reserva Técnica', 3, 'Zona Norte'],
        ] as [$nome, $lider, $regiao]) {
            $equipe = Team::query()->firstOrCreate(
                ['company_id' => $this->empresa->id, 'name' => $nome],
                ['leader_id' => $this->tecnicos[$lider]->id, 'region' => $regiao, 'status' => 'active'],
            );

            $integrantes = array_slice($this->tecnicos, $lider, 2);

            foreach ($integrantes as $posicao => $tecnico) {
                DB::table('team_members')->insert([
                    'team_id' => $equipe->id,
                    'technician_id' => $tecnico->id,
                    'joined_at' => now()->subDays(60 - $posicao)->toDateTimeString(),
                ]);
            }
        }
    }

    /**
     * Ordens de serviço: número, cliente, técnico, serviço, produtos consumidos,
     * prioridade, estado, dia relativo a hoje, hora e duração em horas.
     */
    private function ordens(): void
    {
        $linhas = [
            ['OS-2026-0101', 0, 0, 'CL-01', [], 'normal', 'completed', -20, 8, 2],
            ['OS-2026-0102', 2, 3, 'CF-01', ['CF-500' => 1], 'high', 'completed', -18, 9, 2],
            ['OS-2026-0103', 6, 2, 'CZ-02', ['HY-400' => 1], 'normal', 'completed', -16, 13, 3],
            ['OS-2026-0104', 1, 0, 'CL-02', ['RF-101' => 1], 'normal', 'completed', -12, 8, 2],
            ['OS-2026-0105', 4, 1, 'SB-01', [], 'low', 'completed', -11, 10, 1],
            ['OS-2026-0106', 7, 3, 'CZ-01', ['EL-200' => 2], 'high', 'completed', -9, 14, 3],
            ['OS-2026-0107', 3, 0, 'CL-03', ['RF-100' => 2], 'urgent', 'completed', -8, 7, 4],
            ['OS-2026-0108', 5, 5, 'SB-02', ['VC-300' => 2], 'normal', 'completed', -5, 9, 2],
            ['OS-2026-0109', 8, 1, 'CF-02', ['CF-500' => 1], 'high', 'completed', -4, 11, 1],
            ['OS-2026-0110', 0, 3, 'CL-01', [], 'normal', 'completed', -3, 8, 2],
            ['OS-2026-0111', 2, 0, 'CZ-02', ['HY-400' => 1, 'GE-600' => 1], 'normal', 'completed', -2, 13, 3],
            ['OS-2026-0112', 6, 2, 'CZ-01', ['EL-201' => 2], 'urgent', 'completed', -1, 9, 2],
            ['OS-2026-0113', 1, 1, 'CF-01', [], 'high', 'in_progress', 0, 8, 2],
            ['OS-2026-0114', 4, 2, 'CL-02', ['RF-100' => 1], 'normal', 'in_progress', 0, 10, 3],
            ['OS-2026-0115', 7, 0, 'SB-01', ['VC-300' => 1], 'low', 'on_hold', -2, 9, 1],
            ['OS-2026-0116', 3, 3, 'CZ-01', [], 'urgent', 'open', -1, 14, 2],
            ['OS-2026-0117', 5, 5, 'CL-01', ['EL-200' => 1], 'high', 'open', -2, 15, 1],
            ['OS-2026-0118', 8, 1, 'CF-02', [], 'normal', 'open', 0, 15, 1],
            ['OS-2026-0119', 0, 0, 'CL-03', ['RF-101' => 1], 'high', 'open', 1, 9, 4],
            ['OS-2026-0120', 2, 3, 'SB-02', [], 'normal', 'open', 3, 10, 2],
            ['OS-2026-0121', 6, 2, 'CZ-02', ['HY-400' => 1], 'urgent', 'open', 6, 8, 3],
            ['OS-2026-0122', 4, null, 'CF-01', [], 'normal', 'canceled', -6, 9, 1],
            ['OS-2026-0123', 9, null, 'CL-02', [], 'low', 'canceled', -14, 11, 2],
            ['OS-2026-0124', 1, null, 'SB-01', [], 'normal', 'draft', 9, 9, 1],
            ['OS-2026-0125', 7, null, 'CZ-01', [], 'high', 'draft', 11, 14, 3],
        ];

        foreach ($linhas as $linha) {
            $this->novaOrdem(...$linha);
        }
    }

    private function novaOrdem(
        string $numero,
        int $cliente,
        ?int $tecnico,
        string $servico,
        array $produtos,
        string $prioridade,
        string $status,
        int $dia,
        int $hora,
        int $duracao,
    ): void {
        $agenda = now()->startOfDay()->addDays($dia)->addHours($hora);
        $fim = $agenda->copy()->addHours($duracao);

        $emExecucao = in_array($status, ['in_progress', 'completed'], true);
        $concluida = $status === 'completed';
        $cancelada = $status === 'canceled';

        $ordem = ServiceOrder::query()->create([
            'company_id' => $this->empresa->id,
            'client_id' => $this->clientes[$cliente]->id,
            'service_id' => $this->servicos[$servico]->id,
            'technician_id' => $tecnico === null ? null : $this->tecnicos[$tecnico]->id,
            'number' => $numero,
            'title' => $this->servicos[$servico]->name,
            'description' => 'Chamado aberto pela central de operações da demonstração.',
            'priority' => $prioridade,
            'status' => $status,
            'scheduled_starts_at' => $agenda,
            'scheduled_ends_at' => $fim,
            'started_at' => $emExecucao || $cancelada ? $agenda->copy()->addMinutes(10) : null,
            'completed_at' => $concluida ? $fim->copy()->addMinutes(20) : null,
            'cancelled_at' => $cancelada ? $agenda->copy()->addHours(1) : null,
            'execution_notes' => $concluida
                ? 'Equipamento testado em carga, leitura de temperatura dentro da faixa do fabricante.'
                : null,
            'cancellation_reason' => $cancelada ? 'Cliente remarcou para o mês seguinte.' : null,
            'discount' => $cliente % 3 === 0 && $concluida ? 60.00 : 0,
            ...$this->endereco($cliente),
        ]);

        $itens = [[
            'service_id' => $this->servicos[$servico]->id,
            'product_id' => null,
            'description' => $this->servicos[$servico]->name,
            'quantity' => 1,
            'unit_price' => (float) $this->servicos[$servico]->price,
        ]];

        foreach ($produtos as $sku => $quantidade) {
            $produto = $this->produtoPorSku($sku);

            $itens[] = [
                'service_id' => null,
                'product_id' => $produto->id,
                'description' => $produto->name,
                'quantity' => $quantidade,
                'unit_price' => (float) $produto->price,
            ];
        }

        $ordem->items()->createMany($itens);

        if ($tecnico !== null) {
            ServiceOrderAssignment::query()->create([
                'service_order_id' => $ordem->id,
                'technician_id' => $this->tecnicos[$tecnico]->id,
                'assigned_by' => $this->usuarios['supervisor']->id,
                'assigned_at' => $agenda->copy()->subDays(2),
                'released_at' => $concluida || $cancelada ? $fim : null,
                'note' => $cancelada ? 'Liberado após o cancelamento.' : null,
            ]);
        }

        $this->historico($ordem, $agenda, $fim, $status, $tecnico);

        if ($emExecucao && $tecnico !== null) {
            $this->checkin($ordem, $tecnico, $agenda, $concluida ? $fim->copy()->addMinutes(20) : null);
        }

        $this->ordens[$numero] = $ordem;
    }

    /** @return array<string, mixed> */
    private function endereco(int $cliente): array
    {
        $endereco = $this->clientes[$cliente]->addresses()->first();

        return [
            'street' => $endereco?->street,
            'number_address' => $endereco?->number,
            'complement' => null,
            'neighborhood' => $endereco?->neighborhood,
            'city' => $endereco?->city,
            'state' => $endereco?->state,
            'zip_code' => $endereco?->zip_code,
            'latitude' => $endereco?->latitude,
            'longitude' => $endereco?->longitude,
        ];
    }

    private function historico(ServiceOrder $ordem, $agenda, $fim, string $status, ?int $tecnico): void
    {
        $trilhas = [
            'draft' => [['draft', $agenda->copy()->subDays(1)]],
            'open' => [['draft', $agenda->copy()->subDays(3)], ['open', $agenda->copy()->subDays(2)]],
            'in_progress' => [['draft', $agenda->copy()->subDays(3)], ['open', $agenda->copy()->subDays(2)],
                ['in_progress', $agenda->copy()->addMinutes(10)]],
            'on_hold' => [['draft', $agenda->copy()->subDays(3)], ['open', $agenda->copy()->subDays(2)],
                ['in_progress', $agenda->copy()->addHours(1)], ['on_hold', $agenda->copy()->addHours(2)]],
            'completed' => [['draft', $agenda->copy()->subDays(3)], ['open', $agenda->copy()->subDays(2)],
                ['in_progress', $agenda->copy()->addMinutes(10)], ['completed', $fim->copy()->addMinutes(20)]],
            'canceled' => [['draft', $agenda->copy()->subDays(3)], ['open', $agenda->copy()->subDays(2)],
                ['canceled', $agenda->copy()->addHours(1)]],
        ];

        $anterior = null;

        foreach ($trilhas[$status] as [$destino, $quando]) {
            ServiceOrderStatusHistory::query()->create([
                'service_order_id' => $ordem->id,
                'user_id' => $this->usuarios['supervisor']->id,
                'from_status' => $anterior,
                'to_status' => $destino,
                'note' => $destino === 'canceled'
                    ? 'Cliente remarcou; motivo registrado no chamado.'
                    : ($destino === 'completed' ? 'Serviço concluído e testado no local.' : null),
                'created_at' => $quando,
            ]);

            $anterior = $destino;
        }
    }

    private function checkin(ServiceOrder $ordem, int $tecnico, $entrada, $saida): void
    {
        ServiceOrderCheckin::query()->create([
            'company_id' => $this->empresa->id,
            'service_order_id' => $ordem->id,
            'technician_id' => $this->tecnicos[$tecnico]->id,
            'checkin_at' => $entrada->copy()->addMinutes(12),
            'checkin_latitude' => $ordem->latitude,
            'checkin_longitude' => $ordem->longitude,
            'checkout_at' => $saida,
            'checkout_latitude' => $saida === null ? null : $ordem->latitude,
            'checkout_longitude' => $saida === null ? null : $ordem->longitude,
            'status' => $saida === null ? 'open' : 'closed',
            'observation' => $saida === null ? 'Técnico no local, serviço em andamento.' : 'Saída registrada após o teste.',
        ]);
    }

    private function produtoPorSku(string $sku): Product
    {
        foreach ($this->produtos as $produto) {
            if ($produto->sku === $sku) {
                return $produto;
            }
        }

        throw new \RuntimeException('SKU de demonstração inexistente: '.$sku.'.');
    }

    /**
     * Entradas de compra, cargas ao técnico, consumos das ordens concluídas, uma
     * devolução e um ajuste de inventário. O saldo central é derivado daqui — não
     * existe coluna de quantidade em products.
     */
    private function estoque(): void
    {
        $responsavel = $this->usuarios['supervisor']->id;

        $compras = ['RF-100' => 20, 'RF-101' => 14, 'EL-200' => 6, 'EL-201' => 18,
            'VC-300' => 26, 'HY-400' => 4, 'CF-500' => 12, 'GE-600' => 15];

        foreach ($this->produtos as $produto) {
            StockMovement::query()->create([
                'company_id' => $this->empresa->id,
                'product_id' => $produto->id,
                'user_id' => $responsavel,
                'type' => 'purchase',
                'quantity' => $compras[$produto->sku],
                'unit_cost' => (float) $produto->cost,
                'note' => 'Reposição da compra mensal do fornecedor.',
                'recorded_at' => now()->subDays(30)->setTime(8, 30),
            ]);
        }

        $cargas = [
            [0, 'RF-100', 4], [1, 'CF-500', 3], [2, 'HY-400', 3], [3, 'EL-201', 5],
            [0, 'GE-600', 4], [1, 'VC-300', 6], [3, 'RF-101', 3],
        ];

        foreach ($cargas as $posicao => [$tecnico, $sku, $quantidade]) {
            StockMovement::query()->create([
                'company_id' => $this->empresa->id,
                'product_id' => $this->produtoPorSku($sku)->id,
                'technician_id' => $this->tecnicos[$tecnico]->id,
                'user_id' => $responsavel,
                'type' => 'load',
                'quantity' => $quantidade,
                'note' => 'Carga do veículo na abertura da escala.',
                'recorded_at' => now()->subDays(10 - $posicao)->setTime(7, 45),
            ]);
        }

        // Consumo das peças aplicadas nas ordens concluídas: a baixa é do técnico,
        // com a OS como origem do gasto.
        foreach ($this->ordens as $numero => $ordem) {
            if ($ordem->status !== 'completed' || $ordem->technician_id === null) {
                continue;
            }

            foreach ($ordem->items as $item) {
                if ($item->product_id === null) {
                    continue;
                }

                StockMovement::query()->create([
                    'company_id' => $this->empresa->id,
                    'product_id' => $item->product_id,
                    'technician_id' => $ordem->technician_id,
                    'service_order_id' => $ordem->id,
                    'user_id' => $responsavel,
                    'type' => 'consume',
                    'quantity' => (float) $item->quantity,
                    'unit_cost' => (float) $item->unit_price,
                    'note' => 'Aplicado na '.$numero.', conforme relatório de campo.',
                    'recorded_at' => $ordem->completed_at,
                ]);
            }
        }

        $devolucao = $this->produtoPorSku('VC-300');

        StockMovement::query()->create([
            'company_id' => $this->empresa->id,
            'product_id' => $devolucao->id,
            'technician_id' => $this->tecnicos[1]->id,
            'user_id' => $responsavel,
            'type' => 'return',
            'quantity' => 2,
            'note' => 'Correias que sobraram da escala devolvidas ao almoxarifado.',
            'recorded_at' => now()->subDay()->setTime(18, 0),
        ]);

        StockMovement::query()->create([
            'company_id' => $this->empresa->id,
            'product_id' => $this->produtoPorSku('EL-200')->id,
            'user_id' => $responsavel,
            'type' => 'adjustment',
            'quantity' => -2,
            'note' => 'Inventário: resistências danificadas no estoque, baixa registrada.',
            'recorded_at' => now()->subDays(3)->setTime(17, 15),
        ]);

        $this->estoqueDosTecnicos($responsavel);
    }

    /** Materializa o estoque físico de cada técnico a partir das movimentações. */
    private function estoqueDosTecnicos(int $responsavel): void
    {
        $linhas = DB::table('stock_movements')
            ->where('company_id', $this->empresa->id)
            ->whereNotNull('technician_id')
            ->selectRaw("technician_id, product_id,
                coalesce(sum(case when type = 'load' then quantity
                    when type in ('consume', 'return') then -quantity else 0 end), 0) saldo")
            ->groupBy('technician_id', 'product_id')
            ->get();

        $agora = now();

        DB::table('technician_stocks')->insert($linhas
            ->filter(fn ($linha) => (float) $linha->saldo > 0)
            ->map(fn ($linha) => [
                'company_id' => $this->empresa->id,
                'technician_id' => $linha->technician_id,
                'product_id' => $linha->product_id,
                'quantity' => $linha->saldo,
                'created_at' => $agora,
                'updated_at' => $agora,
            ])
            ->all());
    }

    private function chamados(): void
    {
        $linhas = [
            ['CH-2026-0001', 0, 'Forno combinado sem atingir a temperatura', 'Cozinha e cocção', 'high', 'open', -5, null, null, 2],
            ['CH-2026-0002', 6, 'Câmara fria apitando alarme de porta', 'Refrigeração', 'urgent', 'open', -3, null, null, null],
            ['CH-2026-0003', 3, 'Vitrine com vidro embaçado no self-service', 'Refrigeração', 'normal', 'open', -2, null, null, null],
            ['CH-2026-0004', 8, 'Máquina de espresso perdendo pressão', 'Café e bebidas', 'high', 'open', -1, null, null, 1],
            ['CH-2026-0005', 1, 'Batedeira com ruído anormal na correia', 'Confeitaria profissional', 'normal', 'in_progress', -6, -1, null, 1],
            ['CH-2026-0006', 5, 'Coifa com aspiração fraca', 'Cozinha e cocção', 'low', 'waiting', -9, null, null, 0],
            ['CH-2026-0007', 2, 'Geladeira de massas ligando sem parar', 'Refrigeração', 'high', 'resolved', -12, -4, null, 3],
            ['CH-2026-0008', 7, 'Fogão com acendedor piezo falhando', 'Cozinha e cocção', 'normal', 'resolved', -10, -2, null, null],
            ['CH-2026-0009', 4, 'Aquecedor de xícara desligando sozinho', 'Café e bebidas', 'low', 'resolved', -25, -18, null, null],
            ['CH-2026-0010', 0, 'Estufa exibindo com lâmpada queimada', 'Refrigeração', 'normal', 'closed', -20, -16, -15, null],
            ['CH-2026-0011', 6, 'Soveladeira parando no meio do sova', 'Confeitaria profissional', 'urgent', 'closed', -18, -14, -13, null],
        ];

        foreach ($linhas as [$protocolo, $cliente, $assunto, $categoria, $prioridade, $status, $abertura, $resolucao, $fechamento, $tecnico]) {
            $abertoEm = now()->startOfDay()->addDays($abertura)->setTime(9, 20);
            $resolvidoEm = $resolucao === null ? null : now()->startOfDay()->addDays($resolucao)->setTime(15, 10);
            $fechadoEm = $fechamento === null ? null : now()->startOfDay()->addDays($fechamento)->setTime(10, 0);

            $chamado = Ticket::query()->create([
                'company_id' => $this->empresa->id,
                'client_id' => $this->clientes[$cliente]->id,
                'service_order_id' => $resolucao !== null && $abertura > -12 ? $this->ordens['OS-2026-0111']->id : null,
                'responsible_user_id' => $this->usuarios['supervisor']->id,
                'technician_id' => $tecnico === null ? null : $this->tecnicos[$tecnico]->id,
                'protocol' => $protocolo,
                'subject' => $assunto,
                'description' => 'Relato registrado pelo cliente na central de atendimento da demonstração.',
                'category' => Str::slug($categoria),
                'priority' => $prioridade,
                'status' => $status,
                'opened_at' => $abertoEm,
                'resolved_at' => $resolvidoEm,
                'closed_at' => $fechadoEm,
                'resolution_note' => $resolvidoEm === null ? null : 'Ajuste e teste feitos no local; cliente confirmou o funcionamento.',
            ]);

            $chamado->comments()->create([
                'user_id' => $this->usuarios['supervisor']->id,
                'body' => 'Contato feito com a gerência; técnico designado e peça separada no almoxarifado.',
                'is_internal' => true,
            ]);

            $chamado->comments()->create([
                'client_id' => $this->clientes[$cliente]->id,
                'body' => 'O equipamento voltou a falhar no segundo turno, por isso abri o chamado.',
                'is_internal' => false,
            ]);
        }
    }

    private function agenda(): void
    {
        $compromissos = [
            ['Manutenção na Padaria Sant\'Anna', 'order', 'OS-2026-0118', 0, 15, 1, 'scheduled', 1],
            ['Visita de vistoria — Empório Serra Verde', 'visit', null, 0, 11, 1, 'scheduled', 4],
            ['Instalação de vitrine — Vinha d\'Uva', 'order', 'OS-2026-0119', 1, 9, 4, 'scheduled', 0],
            ['Revisão de soveladeira — Amêndoa Doce', 'order', 'OS-2026-0120', 3, 10, 2, 'scheduled', 3],
            ['Coleta de equipamento para bancada', 'custom', null, 2, 17, 1, 'scheduled', null],
            ['Atendimento emergencial — Forno Alto', 'order', 'OS-2026-0121', 6, 8, 3, 'scheduled', 2],
            ['Escala da semana — equipe Centro', 'custom', null, 3, 8, 1, 'scheduled', null],
            ['Manutenção concluída — Bica Quente', 'order', 'OS-2026-0109', -4, 11, 1, 'completed', 1],
            ['Levantamento de coifa — Dom Tomate', 'order', 'OS-2026-0111', -2, 13, 3, 'completed', 0],
            ['Atendimento remarcado — Lúpulo Bom', 'ticket', 'CH-2026-0004', -1, 14, 2, 'canceled', null],
        ];

        foreach ($compromissos as [$titulo, $tipo, $referencia, $dia, $hora, $duracao, $status, $tecnico]) {
            $inicio = now()->startOfDay()->addDays($dia)->addHours($hora);

            Appointment::query()->create([
                'company_id' => $this->empresa->id,
                'technician_id' => $tecnico === null ? null : $this->tecnicos[$tecnico]->id,
                'client_id' => $this->clienteDaReferencia($referencia, $titulo),
                'service_order_id' => $referencia !== null && str_starts_with($referencia, 'OS-')
                    ? $this->ordens[$referencia]->id
                    : null,
                'ticket_id' => $referencia !== null && str_starts_with($referencia, 'CH-')
                    ? Ticket::query()->where('protocol', $referencia)->value('id')
                    : null,
                'title' => $titulo,
                'description' => 'Janela reservada na escala da equipe.',
                'type' => $tipo,
                'status' => $status,
                'starts_at' => $inicio,
                'ends_at' => $inicio->copy()->addHours($duracao),
                'all_day' => false,
                'location' => $tipo === 'custom' ? 'Base operacional' : 'Endereço do cliente',
            ]);
        }
    }

    private function clienteDaReferencia(?string $referencia, string $titulo): int
    {
        if ($referencia !== null && str_starts_with($referencia, 'OS-')) {
            foreach ($this->ordens as $numero => $ordem) {
                if ($numero === $referencia) {
                    return $ordem->client_id;
                }
            }
        }

        // Título da demonstração traz o cliente no nome; procura pelo cadastro.
        foreach ($this->clientes as $indice => $cliente) {
            if (str_contains($titulo, Str::before($cliente->trade_name ?? $cliente->name, ' '))) {
                return $cliente->id;
            }
        }

        return $this->clientes[0]->id;
    }

    private function financeiro(): void
    {
        $contas = [
            ['OS-2026-0112', 'paid', -1, 'revenue'],
            ['OS-2026-0111', 'paid', -2, 'revenue'],
            ['OS-2026-0110', 'paid', -3, 'revenue'],
            ['OS-2026-0109', 'pending', 7, 'revenue'],
            ['OS-2026-0108', 'paid', -5, 'revenue'],
            ['OS-2026-0107', 'pending', 12, 'revenue'],
            ['OS-2026-0106', 'pending', -3, 'revenue'],
            ['OS-2026-0105', 'paid', -9, 'revenue'],
            ['OS-2026-0104', 'pending', -6, 'revenue'],
            ['OS-2026-0103', 'paid', -16, 'revenue'],
            ['OS-2026-0102', 'canceled', -18, 'revenue'],
        ];

        foreach ($contas as [$numero, $status, $dia, $tipo]) {
            $ordem = $this->ordens[$numero];
            $valor = $this->totalDaOrdem($ordem);

            $lancamento = FinancialRecord::query()->create([
                'company_id' => $this->empresa->id,
                'client_id' => $ordem->client_id,
                'service_order_id' => $ordem->id,
                'type' => $tipo,
                'category' => 'mao_de_obra_e_pecas',
                'description' => 'Cobrança da '.$numero.' — '.$ordem->title,
                'amount' => $valor,
                'due_date' => now()->startOfDay()->addDays($dia)->toDateString(),
                'occurred_at' => $status === 'paid' ? now()->startOfDay()->addDays($dia)->toDateString() : null,
                'status' => $status,
                'notes' => $status === 'canceled' ? 'Ordem cancelada, cobrança sem efeito.' : null,
            ]);

            if ($status === 'paid') {
                Payment::query()->create([
                    'company_id' => $this->empresa->id,
                    'financial_record_id' => $lancamento->id,
                    'user_id' => $this->usuarios['supervisor']->id,
                    'amount' => $valor,
                    'method' => $dia % 2 === 0 ? 'pix' : 'credit_card',
                    'reference' => 'DEMO-'.$ordem->number,
                    'paid_at' => now()->startOfDay()->addDays($dia)->toDateString(),
                    'note' => 'Baixa registrada na demonstração.',
                ]);
            }
        }

        foreach ([
            ['Aluguel da base operacional', 'instalacao', 2800.00, -1, 'paid'],
            ['Combustível da semana', 'deslocamento', 640.00, -2, 'paid'],
            ['Compra de peças do fornecedor Central Frio', 'estoque', 1850.00, -4, 'paid'],
            ['Energia elétrica da base', 'instalacao', 910.00, 6, 'pending'],
        ] as [$descricao, $categoria, $valor, $dia, $status]) {
            $lancamento = FinancialRecord::query()->create([
                'company_id' => $this->empresa->id,
                'type' => 'expense',
                'category' => $categoria,
                'description' => $descricao,
                'amount' => $valor,
                'due_date' => now()->startOfDay()->addDays($dia)->toDateString(),
                'occurred_at' => $status === 'paid' ? now()->startOfDay()->addDays($dia)->toDateString() : null,
                'status' => $status,
            ]);

            if ($status === 'paid') {
                Payment::query()->create([
                    'company_id' => $this->empresa->id,
                    'financial_record_id' => $lancamento->id,
                    'user_id' => $this->usuarios['administrator']->id,
                    'amount' => $valor,
                    'method' => 'transfer',
                    'paid_at' => now()->startOfDay()->addDays($dia)->toDateString(),
                ]);
            }
        }
    }

    private function totalDaOrdem(ServiceOrder $ordem): float
    {
        $subtotal = $ordem->items
            ->sum(fn ($item) => (float) $item->quantity * (float) $item->unit_price);

        return round($subtotal - (float) $ordem->discount, 2);
    }

    private function notificacoes(): void
    {
        foreach ([
            [$this->usuarios['administrator']->id, 'order.created', 'Nova ordem atribuída', 'OS-2026-0112 fechada como concluída no campo.', null, null],
            [$this->usuarios['administrator']->id, 'stock.low', 'Estoque abaixo do ponto', 'Resistência de aquecimento 2.200 W em 4 unidades, mínimo 8.', null, null],
            [$this->usuarios['administrator']->id, 'ticket.opened', 'Chamado urgente sem técnico', 'CH-2026-0002 aberto há 3 dias sem técnico designado.', now()->subHours(5), null],
            [$this->usuarios['administrator']->id, 'financial.overdue', 'Cobrança vencida', 'A cobrança da OS-2026-0106 venceu há 3 dias.', now()->subDay(), null],
            [$this->usuarios['supervisor']->id, 'order.completed', 'Equipe encerrou a escala', 'Duas ordens de hoje concluídas pela equipe Centro.', null, null],
        ] as [$usuario, $tipo, $titulo, $corpo, $leitura, $link]) {
            Notification::query()->create([
                'company_id' => $this->empresa->id,
                'user_id' => $usuario,
                'type' => $tipo,
                'title' => $titulo,
                'body' => $corpo,
                'link' => $link,
                'read_at' => $leitura,
            ]);
        }
    }

    private function resumo(): string
    {
        return ServiceOrder::query()->count().' ordens, '
            .Ticket::query()->count().' chamados, '
            .Client::query()->count().' clientes e '
            .Appointment::query()->count().' compromissos de agenda';
    }
}
