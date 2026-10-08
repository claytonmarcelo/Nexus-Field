<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * As capturas do README são prova de tela, não decoração: cada uma tem que existir,
 * estar referenciada no documento e medir o quadro combinado, senão a vitrine do
 * repositório passa a ilustrar algo que o aplicativo não desenha mais.
 */
class ScreenshotsTest extends TestCase
{
    private const QUADRO = [1440, 900];

    private string $leiaMe;

    private string $pasta;

    protected function setUp(): void
    {
        $raiz = dirname(__DIR__, 2);
        $this->leiaMe = file_get_contents($raiz.'/README.md');
        $this->pasta = $raiz.'/docs/screenshots';
    }

    public function test_o_readme_apresenta_as_seis_telas_do_projeto(): void
    {
        preg_match_all('#<img src="(docs/screenshots/[^"]+\.png)"#', $this->leiaMe, $capturas);

        $this->assertCount(6, $capturas[1]);
        $this->assertSame($capturas[1], array_unique($capturas[1]));

        foreach ($capturas[1] as $caminho) {
            $this->assertFileExists(dirname(__DIR__, 2).'/'.$caminho, "o README aponta para uma tela que não existe: {$caminho}");
        }
    }

    public function test_cada_tela_exibida_no_readme_tem_o_proprio_arquivo_como_link(): void
    {
        preg_match_all('#<a href="(docs/screenshots/[^"]+\.png)"><img src="([^"]+)"#', $this->leiaMe, $pares, PREG_SET_ORDER);

        $this->assertCount(6, $pares, 'toda captura precisa abrir o arquivo em tamanho cheio');

        foreach ($pares as $par) {
            $this->assertSame($par[1], $par[2], 'o link e a imagem mostram arquivos diferentes');
        }
    }

    public function test_todas_as_capturas_medem_o_mesmo_quadro(): void
    {
        $arquivos = glob($this->pasta.'/*.png');

        $this->assertNotEmpty($arquivos);

        foreach ($arquivos as $arquivo) {
            [$largura, $altura] = $this->dimensoes($arquivo);
            $nome = basename($arquivo);

            $this->assertSame(
                self::QUADRO,
                [$largura, $altura],
                "{$nome} mede {$largura}x{$altura}; a série do README é 1440x900."
            );
        }
    }

    public function test_a_legenda_de_cada_tela_bate_com_o_que_o_arquivo_mostra(): void
    {
        $esteiras = [
            '01-boas-vindas.png' => 'tema claro',
            '02-entrada.png' => 'tema escuro',
            '03-recuperar-acesso.png' => 'tema claro',
            '04-painel-claro.png' => 'tema claro',
            '05-painel-escuro.png' => 'tema escuro',
            '06-painel-celular.png' => 'celular',
        ];

        preg_match_all('#<img src="docs/screenshots/([^"]+)" alt="([^"]*)"#', $this->leiaMe, $capturas, PREG_SET_ORDER);

        $descritas = [];
        foreach ($capturas as $captura) {
            $descritas[$captura[1]] = $captura[2];
        }

        foreach ($esteiras as $arquivo => $esperada) {
            $this->assertArrayHasKey($arquivo, $descritas, "{$arquivo} sumiu do README.");
            $this->assertStringContainsString(
                $esperada,
                $descritas[$arquivo],
                "a descrição de {$arquivo} não diz o que a captura mostra ({$esperada})."
            );
        }
    }

    /** @return array{int, int} */
    private function dimensoes(string $arquivo): array
    {
        $cabecalho = unpack('N2', file_get_contents($arquivo, false, null, 16, 8));

        return [$cabecalho[1], $cabecalho[2]];
    }
}
