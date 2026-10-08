<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * A paleta é contrato, não gosto pessoal: estes testes prendem o fundo preto do
 * tema escuro, o branco sem casta do claro, o contraste mínimo do texto e a marca
 * verde medida do medalhão, lendo o `tokens.css` que o navegador vai aplicar.
 */
class PaletteTest extends TestCase
{
    private string $css;

    private string $cssInteiro;

    protected function setUp(): void
    {
        $pasta = dirname(__DIR__, 2).'/resources/css/nexusfield';
        $caminho = $pasta.'/tokens.css';

        $this->assertFileExists($caminho);
        $this->css = file_get_contents($caminho);
        $this->cssInteiro = implode('', array_map('file_get_contents', glob($pasta.'/*.css')))
            .file_get_contents(dirname(__DIR__, 2).'/resources/js/nexusfield/theme.js')
            .file_get_contents(dirname(__DIR__, 2).'/resources/views/components/script/theme.blade.php');
    }

    public function test_fundo_do_tema_escuro_e_preto_e_o_claro_nao_tem_casta_rosada(): void
    {
        $escuro = $this->tokens($this->bloco('[data-bs-theme="dark"]'));
        $claro = $this->tokens($this->bloco(':root,'."\n".'[data-bs-theme="light"]'));

        $this->assertSame('#000000', $escuro['--nf-bg'], 'o fundo do escuro é preto, não grafite azulado');

        [$r, $g, $b] = $this->rgb($claro['--nf-bg']);
        $this->assertLessThanOrEqual($b + 1, $r, 'o fundo claro não pode puxar para o rosado');
        $this->assertNotSame([255, 255, 255], [$r, $g, $b], 'o fundo claro continua sem branco puro');
    }

    public function test_rampas_de_superficie_sao_neutras_nos_dois_temas(): void
    {
        foreach ([
            'escuro' => $this->tokens($this->bloco('[data-bs-theme="dark"]')),
            'claro' => $this->tokens($this->bloco(':root,'."\n".'[data-bs-theme="light"]')),
        ] as $tema => $tokens) {
            foreach (['--nf-bg', '--nf-surface', '--nf-elevated', '--nf-line'] as $token) {
                $casta = $this->spread($tokens[$token]);
                $this->assertLessThanOrEqual(
                    8,
                    $casta,
                    "superfície {$token} do tema {$tema} voltou a ter casta de cor (spread {$casta})"
                );
            }
        }
    }

    public function test_texto_de_todos_os_tons_cumpre_o_contraste_minimo_sobre_as_superficies(): void
    {
        $pares = [
            '--nf-text',
            '--nf-text-muted',
            '--nf-text-faint',
            '--nf-waiting',
        ];

        foreach ([
            'escuro' => $this->tokens($this->bloco('[data-bs-theme="dark"]')),
            'claro' => $this->tokens($this->bloco(':root,'."\n".'[data-bs-theme="light"]')),
        ] as $tema => $tokens) {
            foreach ($pares as $texto) {
                foreach (['--nf-bg', '--nf-surface', '--nf-elevated'] as $fundo) {
                    $razao = $this->contraste($tokens[$texto], $tokens[$fundo]);
                    $this->assertGreaterThanOrEqual(
                        4.5,
                        $razao,
                        sprintf('texto %s sobre %s no tema %s rende %.2f:1', $texto, $fundo, $tema, $razao)
                    );
                }
            }
        }
    }

    public function test_a_letra_que_senta_sobre_a_marca_e_oposta_a_marca_em_cada_tema(): void
    {
        $claro = $this->tokens($this->bloco(':root,'."\n".'[data-bs-theme="light"]'));
        $escuro = $this->tokens($this->bloco('[data-bs-theme="dark"]'));

        $estaClara = fn (string $hex): bool => $this->luminancia($this->rgb($hex)) > .5;

        $this->assertTrue($estaClara($claro['--nf-on-brand']), 'no claro a tinta sobre a marca é clara');
        $this->assertFalse($estaClara($escuro['--nf-on-brand']), 'no escuro a marca é clara, então a tinta sobre ela é escura');

        foreach (['claro' => $claro, 'escuro' => $escuro] as $tema => $tokens) {
            foreach (['--nf-primary', '--nf-success', '--nf-danger', '--nf-warning', '--nf-info'] as $marca) {
                $razao = $this->contraste($tokens['--nf-on-brand'], $tokens[$marca]);
                $this->assertGreaterThanOrEqual(
                    4.5,
                    $razao,
                    sprintf('tinta sobre %s no tema %s rende %.2f:1', $marca, $tema, $razao)
                );
            }
        }
    }

    public function test_as_cores_do_tema_antigo_nao_volta_por_nenhum_canto(): void
    {
        $vetadas = [
            '#101319', '#151922', '#1b2130', '#272e3c', '#171b24', '#121620',
            '#f3eee6', '#faf6ef', '#fdfbf7', '#e5dbca', '#17130c',
        ];

        foreach ($vetadas as $hex) {
            $this->assertStringNotContainsString(
                $hex,
                strtolower($this->cssInteiro),
                "a paleta azulado/rosada anterior trouxe {$hex} de volta"
            );
        }
    }

    public function test_a_marca_e_verde_como_o_medalhao_e_nao_a_teal_anterior(): void
    {
        $vetadas = [
            '#1b6a5b', '#46b39b', '#22836f', '#66c9b2', '#8ad8c4', '#10312b', '#145548',
            '#a4c9c1', '#2c6a5d', '#d9e9e4', '#a7ded1', '#b4e2d6', '#1d5b4f', '#06120f',
            '#a45d1f', '#e0a35f', '#91521b', '#834a18', '#34271a', '#f3e5d3', '#b8804a',
            '#96670f', '#e8bc6a', '#775213', '#eecb8e', '#f3e8cd', '#e0cb94', '#322815', '#7c6134',
            '#8a6212', '#76540f', '#694b0d', '#f0e6cd', '#d9b25c', '#e3c176', '#ecce8d', '#2e2614', '#c79a4a',
        ];

        foreach ($vetadas as $hex) {
            $this->assertStringNotContainsString(
                $hex,
                strtolower($this->cssInteiro),
                "a marca teal/bronze de antes trouxe {$hex} de volta"
            );
        }

        foreach ([
            'claro' => $this->tokens($this->bloco(':root,'."\n".'[data-bs-theme="light"]')),
            'escuro' => $this->tokens($this->bloco('[data-bs-theme="dark"]')),
        ] as $tema => $tokens) {
            foreach (['--nf-primary', '--nf-primary-hover'] as $token) {
                [$r, $g, $b] = $this->rgb($tokens[$token]);
                $this->assertGreaterThan($r, $g, "marca {$token} do tema {$tema} saiu do verde do medalhão");
                $this->assertGreaterThan($b, $g, "marca {$token} do tema {$tema} puxou para o ciano");
            }
        }
    }

    /**
     * O menu veste o tema da página. No claro ele é a superfície elevada da casa,
     * então a tinta do item precisa vir dos tokens do tema — e não de um hex claro
     * herdado do painel escuro que a sidebar já foi.
     */
    public function test_o_menu_do_tema_claro_le_no_painel_claro_com_contraste_minimo(): void
    {
        $claro = $this->tokens($this->bloco(':root,'."\n".'[data-bs-theme="light"]'));
        $menu = $this->tokens($this->blocoDeLinha('.app-sidebar'));

        $estaNoMenu = ['--lte-sidebar-color', '--lte-sidebar-menu-active-color', '--lte-sidebar-header-color'];

        foreach ($estaNoMenu as $token) {
            $this->assertArrayHasKey($token, $menu, "{$token} sumiu do bloco .app-sidebar");
            $this->assertMatchesRegularExpression(
                '/^var\(--nf-[a-z-]+\)$/',
                $menu[$token],
                "o item {$token} do menu claro voltou a ter hex próprio em vez de token do tema"
            );
        }

        $tinta = fn (string $token): string => $claro[substr($menu[$token], 4, -1)];

        $pares = [
            '--lte-sidebar-color' => $claro['--nf-elevated'],
            '--lte-sidebar-header-color' => $claro['--nf-elevated'],
            '--lte-sidebar-menu-active-color' => $claro['--nf-primary-soft'],
        ];

        foreach ($pares as $token => $fundo) {
            $razao = $this->contraste($tinta($token), $fundo);
            $this->assertGreaterThanOrEqual(
                4.5,
                $razao,
                sprintf('menu claro: %s sobre %s rende %.2f:1', $token, $fundo, $razao)
            );
        }

        // O escuro continua com a tinta própria do painel preto, medida à parte.
        $escuro = $this->tokens($this->blocoDeLinha('[data-bs-theme="dark"] .app-sidebar'));
        $this->assertArrayHasKey('--lte-sidebar-color', $escuro, 'o menu escuro perdeu a própria tinta');
    }

    private function bloco(string $seletor): string
    {
        $inicio = strpos($this->css, $seletor);
        $this->assertNotFalse($inicio, "bloco {$seletor} não existe em tokens.css");

        $fim = strpos($this->css, "\n}", $inicio);
        $this->assertNotFalse($fim);

        return substr($this->css, $inicio, $fim - $inicio);
    }

    /**
     * Bloco cujo seletor abre a linha — é assim que se pega `.app-sidebar` sem
     * cair no texto de um comentário que cite a mesma classe.
     */
    private function blocoDeLinha(string $seletor): string
    {
        $localizado = preg_match(
            '/^'.preg_quote($seletor, '/').'\s*\{\n(.*?)^\}/ms',
            $this->css,
            $casos,
        );

        $this->assertSame(1, $localizado, "bloco {$seletor} não existe em tokens.css");

        return $casos[1];
    }

    /** @return array<string, string> */
    private function tokens(string $bloco): array
    {
        preg_match_all('/(--[a-z0-9-]+):\s*([^;]+);/', $bloco, $casos, PREG_SET_ORDER);

        $tokens = [];
        foreach ($casos as $caso) {
            $tokens[$caso[1]] = trim($caso[2]);
        }

        return $tokens;
    }

    /** @return array{int, int, int} */
    private function rgb(string $valor): array
    {
        $hex = ltrim($valor, '#');
        $this->assertMatchesRegularExpression('/^[0-9a-f]{3}$|^[0-9a-f]{6}$/i', $hex, "valor não é hex: {$valor}");

        if (strlen($hex) === 3) {
            $hex = implode('', array_map(fn (string $c): string => $c.$c, str_split($hex)));
        }

        return [
            (int) hexdec(substr($hex, 0, 2)),
            (int) hexdec(substr($hex, 2, 2)),
            (int) hexdec(substr($hex, 4, 2)),
        ];
    }

    private function spread(string $valor): int
    {
        $canais = $this->rgb($valor);

        return max($canais) - min($canais);
    }

    private function luminancia(array $rgb): float
    {
        $lineares = array_map(
            static function (int $canal): float {
                $c = $canal / 255;

                return $c <= .04045 ? $c / 12.92 : (($c + .055) / 1.055) ** 2.4;
            },
            $rgb
        );

        return .2126 * $lineares[0] + .7152 * $lineares[1] + .0722 * $lineares[2];
    }

    private function contraste(string $frente, string $fundo): float
    {
        $a = $this->luminancia($this->rgb($frente));
        $b = $this->luminancia($this->rgb($fundo));
        [$maior, $menor] = [$a >= $b ? $a : $b, $a >= $b ? $b : $a];

        return ($maior + .05) / ($menor + .05);
    }
}
