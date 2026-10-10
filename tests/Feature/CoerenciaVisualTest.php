<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\CreatesFixtures;
use Tests\TestCase;

/**
 * O briefing "Premium Gourmet" fecha na camada de apresentação, e apresentação que
 * ninguém veste é entropia. Estas provas amarram o global ponta a ponta: a espera
 * real da agenda tem esqueleto em vez de silêncio, a virada de tema desliza em
 * duzentos milissegundos sem pintar a recarga, nenhum caminho de rede é escrito duas
 * vezes na mesma tela, a folha não declara a mesma propriedade para o mesmo seletor
 * (o primeiro valor nunca é pintado), nenhuma classe `nf-` fica esperando uso de uma
 * interface que não existe, nenhuma palavra se parte ao meio porque o layout cedeu
 * antes dela, a tela aberta oferece uma só porta de entrada, o botão do menu desenha
 * o próprio estado em vez de trocar de glifo, a abertura da boas-vindas fecha em
 * faixa própria em vez de vão, o hífen da casca pública não vira quebra de palavra, e
 * o rótulo escondido do cabeçalho ordenável não empurra a janela para fora da tela.
 */
class CoerenciaVisualTest extends TestCase
{
    use CreatesFixtures, RefreshDatabase;

    private const FOLHAS = ['base', 'components', 'listings', 'agenda', 'public', 'tokens'];

    public function test_a_espera_da_agenda_desenha_esqueleto_em_vez_de_silencio(): void
    {
        $this->seedPermissions();
        $usuario = $this->makeUser('administrator', $this->makeCompany('alfa'), 'a@test.local');

        $html = preg_replace('/\s+/', ' ', $this->actingAs($usuario)
            ->get(route('agenda.index'))
            ->assertOk()
            ->getContent());

        $this->assertStringContainsString('data-nf-esqueleto', $html);
        $this->assertStringContainsString('nf-agenda-esqueleto" data-nf-esqueleto hidden', $html,
            'A faixa nasce escondida: ela aparece quando a consulta sai, não quando a tela abre.');
        $this->assertSame(5, substr_count($html, 'class="nf-skeleton"'),
            'O esqueleto tem de desenhar as linhas que o quadro vai preencher.');

        // A faixa é irmã do quadro porque o FullCalendar escreve dentro dele.
        $this->assertLessThan(strpos($html, 'data-nf-agenda'), strpos($html, 'data-nf-esqueleto'));

        // Quem conduz a faixa é o callback de carregamento do próprio calendário, e
        // ele fala com o leitor de tela: sem aria-busy a espera seria uma agenda
        // vazia contada duas vezes.
        $js = file_get_contents(base_path('resources/js/nexusfield/agenda.js'));
        $this->assertStringContainsString('loading: (buscando) => mostrarEsqueleto(esqueleto, elemento, buscando)', $js);
        $this->assertStringContainsString("elemento.setAttribute('aria-busy', buscando ? 'true' : 'false')", $js);

        $css = $this->folha('agenda');
        $this->assertStringContainsString('.nf-agenda-esqueleto[hidden]', $css,
            'Sem o display:none da faixa escondida o grid do CSS venceria o atributo hidden.');
        $this->assertStringContainsString('.nf-agenda.is-carregando', $css);
    }

    public function test_a_virada_de_tema_desliza_e_a_pintura_inicial_continua_imediata(): void
    {
        $css = $this->folha('base');
        $inicio = strpos($css, 'A virada de tema é a única cor que desliza');
        $this->assertNotFalse($inicio, 'A janela da virada não está comentada na folha de base.');

        $fim = strpos($css, '@media (prefers-reduced-motion', $inicio);
        $bloco = substr($css, $inicio, $fim - $inicio);

        $this->assertStringContainsString('html.nf-tema-virando *', $bloco);
        $this->assertStringContainsString('background-color .2s var(--nf-ease)', $bloco);
        $this->assertStringContainsString('border-color .2s var(--nf-ease)', $bloco);

        // A janela anima fundo, fio e letra: quem já tinha gesto próprio — o hover
        // do cartão, o assentamento do anel — continua mandando no seu.
        $this->assertStringNotContainsString('transform', $bloco);
        $this->assertStringNotContainsString('width', $bloco);

        // A régua de quem pediu calma continua mandando sobre a janela nova.
        $this->assertMatchesRegularExpression(
            '/prefers-reduced-motion: reduce[\s\S]{0,260}transition-duration: \.01ms !important/',
            $css
        );

        $js = file_get_contents(base_path('resources/js/nexusfield/theme.js'));
        $this->assertStringContainsString("const CLASSE_DA_VIRADA = 'nf-tema-virando';", $js);
        $this->assertStringContainsString('raiz.classList.add(CLASSE_DA_VIRADA)', $js);
        $this->assertStringContainsString('raiz.classList.remove(CLASSE_DA_VIRADA)', $js);
        $this->assertStringContainsString('const mudou = document.documentElement.dataset.bsTheme !== mode;', $js,
            'Se o tema já é o pedido, nada desliza: a virada anima troca de verdade.');

        // O script inline do <head> pinta antes da primeira pintura e não anima:
        // transição na recarga seria a tela subindo do branco.
        $head = file_get_contents(base_path('resources/views/components/script/theme.blade.php'));
        $this->assertStringNotContainsString('nf-tema-virando', $head);
    }

    public function test_o_esqueleto_e_a_virada_vestem_o_registro_da_casa_sem_tinta_nova(): void
    {
        $agenda = $this->semComentario($this->folha('agenda'));
        $base = $this->semComentario($this->folha('base'));

        $inicioEsqueleto = strpos($agenda, '.nf-agenda-esqueleto {');
        $inicioVirada = strpos($base, 'html.nf-tema-virando *');
        $this->assertNotFalse($inicioEsqueleto, 'O esqueleto da agenda não está na folha.');
        $this->assertNotFalse($inicioVirada, 'A janela da virada não está na folha de base.');

        $esqueleto = substr($agenda, $inicioEsqueleto, 900);
        $virada = substr($base, $inicioVirada, 400);

        foreach (['esqueleto da agenda' => $esqueleto, 'virada de tema' => $virada] as $rotulo => $bloco) {
            $this->assertMatchesRegularExpression('/var\(--nf-/', $bloco, "O {$rotulo} tem de beber dos tokens.");
            $this->assertDoesNotMatchRegularExpression('/#[0-9a-fA-F]{3,8}\b/', $bloco,
                "O {$rotulo} trouxe uma tinta fora do que os dois temas já definem.");
            $this->assertStringNotContainsString('rgb(', $bloco);
        }
    }

    /**
     * A folha não guarda peça de vitrine. Cada classe nf- definida nos seis
     * arquivos de estilo tem de aparecer em algum Blade, em algum script, em algum
     * controller que monta markup ou em alguma seed — classe sem dono é a duplicação
     * que o briefing vetou: duas maneiras de escrever a mesma coisa, uma delas nunca
     * lida por ninguém.
     */
    public function test_nenhuma_classe_da_folha_espera_uso_na_interface(): void
    {
        $definidas = [];

        foreach (self::FOLHAS as $folha) {
            preg_match_all('/\.(nf[a-zA-Z0-9_-]*)/', $this->semComentario($this->folha($folha)), $acertos);

            foreach ($acertos[1] as $classe) {
                $definidas[$classe] = $folha;
            }
        }

        $this->assertGreaterThan(150, count($definidas), 'A varredura perdeu as folhas de estilo.');

        $interface = $this->interface();
        $orfas = [];

        foreach ($definidas as $classe => $folha) {
            if (str_contains($interface, $classe)) {
                continue;
            }

            // Sufixo montado em tempo de execução — nf-fc-t--{tom},
            // nf-brand-mark--{animação}: o tronco é o contrato, o resto vem do
            // catálogo de estados.
            $tronco = strstr($classe, '--', true);

            if ($tronco !== false && str_contains($interface, $tronco.'--')) {
                continue;
            }

            $orfas[] = $folha.'.css -> .'.$classe;
        }

        $this->assertSame([], $orfas, 'Classes que nenhuma interface veste: '.implode(', ', $orfas));
    }

    public function test_a_folha_para_de_se_desmentir_no_mesmo_seletor(): void
    {
        $repetidas = [];

        foreach (self::FOLHAS as $folha) {
            $vistas = [];

            foreach ($this->blocosNivelUm($this->folha($folha)) as $bloco) {
                foreach ($this->propriedades($bloco['corpo']) as $propriedade) {
                    $chave = $bloco['seletor'].'{'.$propriedade.'}';

                    if (isset($vistas[$chave])) {
                        $repetidas[] = $folha.'.css -> '.$bloco['seletor'].' { '.$propriedade.' }';
                    }

                    $vistas[$chave] = true;
                }
            }
        }

        $this->assertSame(
            [],
            $repetidas,
            'Propriedade declarada duas vezes para o mesmo seletor, e a primeira nunca é pintada: '
                .implode(', ', $repetidas)
        );
    }

    public function test_os_dois_pedidos_da_agenda_caminham_pela_mesma_estrada(): void
    {
        $js = file_get_contents(base_path('resources/js/nexusfield/agenda.js'));

        // Eram dois blocos de fetch quase idênticos: cabeçalho, corpo e as duas caras
        // do erro escritos duas vezes, que é exatamente como uma mensagem de rede
        // passa a estar certa num botão e errada no outro.
        $this->assertSame(1, substr_count($js, 'await fetch('), 'A agenda voltou a ter dois caminhos de rede.');
        $this->assertStringContainsString('async function pedido(url', $js);
        $this->assertStringContainsString('class Recusa extends Error', $js);
        $this->assertStringContainsString('class SemConexao extends Error', $js);
        $this->assertStringContainsString("toast.error(erro.message, 'A agenda não abriu');", $js);
        $this->assertStringContainsString("toast.error(erro.message, 'A janela não mudou');", $js);

        // A escrita por arrasto agora tem cara enquanto o banco não responde.
        $this->assertStringContainsString("info.el?.classList.toggle('is-gravando', ativa)", $js);

        $css = $this->folha('agenda');
        $inicio = strpos($css, '.nf-agenda .fc-event.is-gravando');
        $this->assertNotFalse($inicio, 'O estado de escrita da agenda sumiu da folha.');

        $bloco = substr($css, $inicio, strpos($css, '}', $inicio) - $inicio);
        $this->assertMatchesRegularExpression('/var\(--nf-/', $bloco, 'O estado de escrita pinta fora do registro de tokens.');
        $this->assertDoesNotMatchRegularExpression('/#[0-9a-fA-F]{3,8}|rgb\(/', $bloco, 'O estado de escrita trouxe tinta nova.');
    }

    /**
     * A régua de legibilidade da casa: o meio da palavra não se parte porque o layout
     * precisou ceder espaço. `overflow-wrap: anywhere` entra na conta do min-content,
     * então a coluna encolhe até cortar o rótulo em duas metades que ninguém lê como
     * uma palavra só. `break-word` fica fora dessa conta — quem cede é o layout, e
     * partir a palavra volta a ser o último recurso que sempre foi.
     */
    public function test_a_palavra_nunca_parte_no_meio_por_causa_do_layout(): void
    {
        foreach (self::FOLHAS as $folha) {
            $this->assertStringNotContainsString(
                'overflow-wrap: anywhere',
                $this->semComentario($this->folha($folha)),
                $folha.'.css voltou a deixar a largura partir a palavra.'
            );
        }

        $base = $this->folha('base');

        // Título e parágrafo decidem a linha antes de o leitor chegar: sem as duas
        // palavras-chave a última linha cai com uma palavra solta.
        $this->assertMatchesRegularExpression('/text-wrap:\s*pretty/', $this->bloco($base, 'p'),
            'O parágrafo voltou a terminar com uma palavra órfã na última linha.');
        $this->assertMatchesRegularExpression(
            '/text-wrap:\s*balance/',
            $this->bloco($base, 'h1, h2, h3, .h1, .h2, .h3, .nf-display'),
            'O título enche a primeira linha e joga o resto sozinho na seguinte.'
        );

        $componentes = $this->folha('components');
        $linha = $this->bloco($componentes, '.nf-fact-list li');
        $this->assertMatchesRegularExpression('/flex-wrap/', $linha,
            'Sem a linha voltar, o valor não desce inteiro: ele é espremido até partir.');

        $rotulo = $this->bloco($componentes, '.nf-fact-list li > span');
        $this->assertStringContainsString('flex: 0 0 auto', $rotulo);
        $this->assertStringNotContainsString('min-width', $rotulo,
            'Rótulo que negocia largura é rótulo que se parte no meio.');

        $valor = $this->bloco($componentes, '.nf-fact-list li > strong');
        $this->assertStringContainsString('flex: 0 1 auto', $valor);
        $this->assertStringContainsString('min-width: 0', $valor,
            'Quem tem folga para encolher é o valor, nunca a etiqueta.');
    }

    /**
     * A tabela larga rola por dentro do cartão de propósito, e o recorte que faz isso
     * só vale para o que se posiciona dentro dele. O rótulo que o cabeçalho ordenável
     * entrega ao leitor de tela é `position: absolute` — é como o Bootstrap esconde
     * uma palavra sem tirá-la da fala — e, enquanto o rolador não era bloco contenedor
     * ele se media pelo cartão, que é `position: relative`, furando o recorte: em 768px
     * a coluna "Situação" começa a 801px, o documento tinha 810px e a janela arrastava
     * 42px para o lado. A prova amarra os dois lados do contrato — o pixel continua
     * existindo para quem ouve, e deixou de existir para quem rola.
     */
    public function test_o_rolador_e_dono_do_que_se_posiciona_dentro_dele(): void
    {
        $componentes = $this->folha('components');
        $rolador = $this->bloco($componentes, '.table-responsive');

        $this->assertStringContainsString('position: relative', $rolador,
            'Sem o rolador como bloco contenedor, o rótulo absoluto do cabeçalho escapa '
                .'do recorte e abre rolagem horizontal na janela.');
        $this->assertStringContainsString('overscroll-behavior-x: contain', $rolador,
            'O arrasto continua morrendo na tabela: o que saiu foi o vazamento, não a rolagem.');

        // A premissa da regra: o cabeçalho ordenável ainda fala o próprio estado para
        // quem não vê a seta.
        $cabecalho = file_get_contents(base_path('resources/views/components/ui/sort-link.blade.php'));
        $this->assertStringContainsString('class="visually-hidden"', $cabecalho,
            'Se o cabeçalho deixou de ter rótulo escondido, a regra perdeu o motivo.');
        $this->assertStringContainsString('aria-sort=', $cabecalho);
    }

    /**
     * A tela aberta tem uma porta só por altura de página: o canto superior direito
     * chama para entrar e o bloco final fecha a chamada. Um terceiro botão no meio do
     * hero não leva a lugar novo nenhum — só disputa o clique com os outros dois.
     */
    public function test_a_boas_vindas_abre_uma_soa_porta_em_vez_de_tres(): void
    {
        $html = $this->get(route('welcome'))->assertOk()->getContent();

        $this->assertSame(2, substr_count($html, 'class="btn btn-'),
            'A página aberta voltou a oferecer mais de uma chamada para entrar.');

        $hero = $this->recorte($html, 'class="nf-hero"', '</section>');
        $this->assertStringNotContainsString('class="btn', $hero,
            'O meio da página chamou o clique de novo: a porta é do cabeçalho.');
        $this->assertStringNotContainsString('Acessar a plataforma', $html);
        $this->assertStringNotContainsString('Ver os módulos', $html);

        // O que saiu foi o botão, não o destino: a âncora da vitrine continua lá
        // para quem chega por um link externo.
        $this->assertStringContainsString('id="modulos"', $html);
        $this->assertStringContainsString('href="'.route('login').'"', $html);
    }

    /**
     * A abertura não pode terminar no meio do próprio texto. A esteira do ciclo morava
     * embaixo do medalhão, numa coluna de cinco doze avos, e o lado da letra ficava sem
     * chão: tirada de lá, ela virou faixa larga irmã do row, e o hero passa a fechar no
     * conteúdo que atravessa a página inteira.
     */
    public function test_a_abertura_da_boas_vindas_fecha_em_faixa_propria(): void
    {
        $html = $this->get(route('welcome'))->assertOk()->getContent();
        $hero = $this->recorte($html, 'class="nf-hero"', '</section>');

        $medalhao = $this->recorte($hero, 'class="col-12 col-lg-5"', '</div>');
        $this->assertStringContainsString('nf-brand-stage-logo', $medalhao);
        $this->assertStringNotContainsString('nf-flow-panel', $medalhao,
            'A esteira voltou a morar embaixo do medalhão: era ela que abria o vão na coluna do texto.');

        $painel = $this->recorte($hero, 'nf-flow-panel', '</div>');
        $this->assertSame(6, substr_count($painel, '<li>'),
            'O ciclo é a esteira da casa: seis passos, na ordem em que eles acontecem.');
        $this->assertMatchesRegularExpression('/<\/ol>\s*<p class="nf-text-muted-2/', $painel,
            'A faixa precisa dizer onde desemboca: sem fecho, a esteira é só uma lista de desejos.');

        $publica = $this->folha('public');
        $this->assertMatchesRegularExpression('/margin-top/', $this->bloco($publica, '.nf-hero-ciclo'),
            'A faixa sem respiro próprio cola na letra que fecha o hero.');
        $this->assertMatchesRegularExpression('/margin-top/', $this->bloco($publica, '.nf-hero-text + .nf-hero-text'),
            'Os dois fôlegos do bloco de texto voltaram a ser digitados um em cima do outro.');
    }

    /**
     * Hífen comum é oportunidade de quebra para o quebra-linhas: "e-" no fim de uma
     * linha e "mail" na seguinte é a mesma palavra que ninguém lê como uma só. Na
     * prosa das telas abertas a casa escreve esses termos com o hífen que não quebra
     * (U+2011). O nome da marca fica fora da régua — ele é escrito com o traço do
     * registro em todo lugar, e uma etiqueta de formulário sozinha nunca parte.
     */
    public function test_o_hifen_da_casca_publica_nao_vira_quebra_de_palavra(): void
    {
        $paginas = [
            route('welcome') => 'boas-vindas',
            route('login') => 'entrada',
            route('password.request') => 'recuperação',
            '/endereco-que-nao-existe' => 'não encontrada',
        ];

        foreach ($paginas as $url => $rotulo) {
            $html = preg_replace('/<!--[\s\S]*?-->/', '', $this->get($url)->getContent());

            $esta = [];

            // Só o que corre em parágrafo, título ou item: é aí que a linha se enche
            // e o traço vira corte.
            preg_match_all('/<(p|h[1-3]|li)\b[^>]*>(.*?)<\/\1>/s', $html, $blocos);

            foreach ($blocos[2] as $trecho) {
                preg_match_all('/[\p{L}]+-[\p{L}]+/u', trim(strip_tags($trecho)), $acertos);

                foreach ($acertos[0] as $palavra) {
                    if ($palavra !== 'NEXUS-FIELD' && $palavra !== 'Nexus-Field') {
                        $esta[] = $palavra;
                    }
                }
            }

            $this->assertSame([], array_unique($esta),
                "A página {$rotulo} voltou a oferecer um hífen que quebra a palavra: "
                    .implode(', ', array_unique($esta)));
        }
    }

    /**
     * O botão do menu não troca de glifo: ele dobra. Três barras desenhadas em CSS e
     * uma única propriedade, `--nf-dobra`, contam se o menu está aberto ou recolhido —
     * o glifo de biblioteca não tem para onde ir, e um ícone que vira outro é a
     * mesma informação escrita duas vezes, uma delas sempre atrasada no clique.
     */
    public function test_o_botao_do_menu_desenha_o_estado_em_vez_de_trocar_de_glifo(): void
    {
        $navbar = file_get_contents(base_path('resources/views/components/app/navbar.blade.php'));

        $this->assertStringNotContainsString('fa-bars', $navbar,
            'O hambúrguer voltou a ser glifo de biblioteca: ícone não tem estado.');
        $this->assertStringContainsString('data-lte-toggle="sidebar"', $navbar,
            'Quem manda no menu continua sendo o contrato do AdminLTE, não um script próprio.');
        $this->assertStringContainsString('data-nf-menu-toggle', $navbar);
        $this->assertStringContainsString('aria-controls="navigation"', $navbar,
            'O botão precisa dizer ao leitor de tela qual região ele governa.');
        $this->assertSame(3, substr_count($navbar, 'class="nf-nav-toggle-bar"'),
            'As três barras são o desenho da peça: faltou barra.');

        $componentes = $this->folha('components');

        $botao = $this->bloco($componentes, '.nf-nav-toggle');
        $this->assertStringContainsString('--nf-dobra: 0', $botao,
            'O estado abre a folha como variável, e não como classe de tinta.');
        $this->assertMatchesRegularExpression('/var\(--nf-/', $botao);
        $this->assertDoesNotMatchRegularExpression('/#[0-9a-fA-F]{3,8}|rgb\(/', $botao,
            'A peça trouxe tinta fora dos dois temas que a casa já registra.');

        $barra = $this->bloco($componentes, '.nf-nav-toggle-bar');
        $this->assertStringContainsString('background-color: currentColor', $barra,
            'A barra herda a tinta do botão: uma propriedade só, sem jogo de classe.');
        $this->assertStringContainsString('transform-origin: right center', $barra);

        foreach (['.nf-nav-toggle-bar:nth-child(1)', '.nf-nav-toggle-bar:nth-child(3)'] as $seletor) {
            $this->assertMatchesRegularExpression('/calc\(var\(--nf-dobra\)/', $this->bloco($componentes, $seletor),
                $seletor.' não acompanha a dobra do menu.');
        }

        // Os dois quebradores do template são os únicos donos do estado: largo com o
        // corpo recolhido, estreito com o corpo aberto.
        $this->assertMatchesRegularExpression(
            '/@media \(min-width: 992px\)[\s\S]{0,140}body\.sidebar-collapse \.nf-nav-toggle \{\s*--nf-dobra: 1/',
            $componentes
        );
        $this->assertMatchesRegularExpression(
            '/@media \(max-width: 991\.98px\)[\s\S]{0,200}body\.sidebar-open \.nf-nav-toggle \{\s*--nf-dobra: 0/',
            $componentes
        );

        $menu = base_path('resources/js/nexusfield/menu.js');
        $this->assertFileExists($menu);
        $js = file_get_contents($menu);

        $this->assertStringContainsString("document.querySelectorAll('[data-nf-menu-toggle]')", $js);
        $this->assertStringContainsString("attributeFilter: ['class']", $js,
            'O espelho lê a classe do corpo: sem observer o rótulo ficaria para trás.');
        $this->assertStringContainsString("setAttribute('aria-expanded'", $js);
        $this->assertStringNotContainsString('classList.toggle', $js,
            'O script não manda no menu; ele só conta o que o template já decidiu.');

        $app = file_get_contents(base_path('resources/js/app.js'));
        $this->assertStringContainsString("import * as menu from './nexusfield/menu';", $app);
        $this->assertStringContainsString('menu.init();', $app);
    }

    /**
     * Corpo de um bloco de topo, procurado pelo seletor normalizado. A prova fala com
     * a regra, não com o arquivo inteiro: um `assertStringContainsString` solto acharia
     * a palavra dentro de um comentário e declararia vestida uma peça que não existe.
     */
    private function bloco(string $css, string $seletor): string
    {
        foreach ($this->blocosNivelUm($css) as $bloco) {
            if ($bloco['seletor'] === $seletor) {
                return $bloco['corpo'];
            }
        }

        $this->fail('Seletor de topo não encontrado na folha: '.$seletor);
    }

    /**
     * Recorte entre um marcador e o primeiro fecho que vem depois dele, para a prova
     * poder dizer "dentro do hero" em vez de "em algum lugar da página".
     */
    private function recorte(string $html, string $abre, string $fecha): string
    {
        $inicio = strpos($html, $abre);
        $this->assertNotFalse($inicio, 'O recorte não achou a abertura: '.$abre);

        $fim = strpos($html, $fecha, $inicio);
        $this->assertNotFalse($fim, 'O recorte não achou o fecho: '.$fecha);

        return substr($html, $inicio, $fim - $inicio);
    }

    /**
     * Blocos de topo da folha, com o seletor normalizado. @media/@keyframes são
     * contexto, não regra: quem desmente quem precisa estar na mesma altura do
     * arquivo, senão a comparação é entre telas diferentes.
     *
     * @return array<int, array{seletor: string, corpo: string}>
     */
    private function blocosNivelUm(string $css): array
    {
        $limpo = preg_replace_callback(
            '/\/\*[\s\S]*?\*\//',
            fn (array $m): string => preg_replace('/[^\n]/', ' ', $m[0]),
            $css
        );

        $blocos = [];
        $contexto = [];
        $i = 0;
        $n = strlen($limpo);

        while ($i < $n) {
            $abre = strpos($limpo, '{', $i);

            if ($abre === false) {
                break;
            }

            // O fecho do @media não é seletor: sem andar o ponteiro, ele colaria no
            // seletor do bloco seguinte e as duas metades do arquivo deixariam de se
            // reconhecer como o mesmo seletor.
            while ($contexto !== [] && $abre >= $contexto[count($contexto) - 1]['fim']) {
                $i = max($i, $contexto[count($contexto) - 1]['fim']);
                array_pop($contexto);
            }

            if ($i > $abre) {
                continue;
            }

            $seletor = trim(preg_replace('/\s+/', ' ', substr($limpo, $i, $abre - $i)));
            $profundidade = 1;
            $fim = $abre + 1;

            while ($fim < $n && $profundidade > 0) {
                if ($limpo[$fim] === '{') {
                    $profundidade++;
                }

                if ($limpo[$fim] === '}') {
                    $profundidade--;
                }

                $fim++;
            }

            if (str_starts_with($seletor, '@')) {
                $contexto[] = ['sel' => $seletor, 'fim' => $fim];
                $i = $abre + 1;

                continue;
            }

            if ($contexto === []) {
                $blocos[] = ['seletor' => $seletor, 'corpo' => substr($limpo, $abre + 1, $fim - $abre - 2)];
            }

            $i = $fim;
        }

        $this->assertNotEmpty($blocos, 'A varredura de blocos não achou regra nenhuma.');

        return $blocos;
    }

    /**
     * Nomes de propriedade de um corpo de bloco, cortando no ';' que está fora de
     * parêntese: gradiente e color-mix() carregam vírgula e parêntese no valor, e é
     * o parêntese que protege a leitura.
     *
     * @return array<int, string>
     */
    private function propriedades(string $corpo): array
    {
        $nomes = [];
        $pedaco = '';
        $parenteses = 0;

        for ($k = 0; $k < strlen($corpo); $k++) {
            $c = $corpo[$k];

            if ($c === '(') {
                $parenteses++;
            } elseif ($c === ')') {
                $parenteses--;
            } elseif ($c === ';' && $parenteses === 0) {
                $nomes[] = $this->nome($pedaco);
                $pedaco = '';

                continue;
            }

            $pedaco .= $c;
        }

        $nomes[] = $this->nome($pedaco);

        return array_values(array_filter($nomes));
    }

    private function nome(string $declaracao): ?string
    {
        $dois = strpos($declaracao, ':');

        if ($dois === false || str_starts_with(trim($declaracao), '@')) {
            return null;
        }

        return trim(substr($declaracao, 0, $dois)) ?: null;
    }

    private function folha(string $nome): string
    {
        $caminho = base_path('resources/css/nexusfield/'.$nome.'.css');
        $this->assertFileExists($caminho);

        return file_get_contents($caminho);
    }

    private function semComentario(string $css): string
    {
        return preg_replace('/\/\*[\s\S]*?\*\//', '', $css);
    }

    private function interface(): string
    {
        $corpus = '';

        foreach (['resources/views', 'resources/js', 'app', 'database'] as $pasta) {
            $itens = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator(base_path($pasta), \FilesystemIterator::SKIP_DOTS)
            );

            foreach ($itens as $arquivo) {
                if ($arquivo->isFile() && preg_match('/\.(php|js)$/', $arquivo->getFilename())) {
                    $corpus .= file_get_contents($arquivo->getPathname());
                }
            }
        }

        return $corpus;
    }
}
