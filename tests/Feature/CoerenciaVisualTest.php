<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\CreatesFixtures;
use Tests\TestCase;

/**
 * O briefing "Premium Gourmet" fecha na camada de apresentação, e apresentação que
 * ninguém veste é entropia. Estas provas amarram o global em cinco pontas: a espera
 * real da agenda tem esqueleto em vez de silêncio, a virada de tema desliza em
 * duzentos milissegundos sem pintar a recarga, nenhum caminho de rede é escrito duas
 * vezes na mesma tela, a folha não declara a mesma propriedade para o mesmo seletor
 * (o primeiro valor nunca é pintado), e nenhuma classe `nf-` fica esperando uso de
 * uma interface que não existe.
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
