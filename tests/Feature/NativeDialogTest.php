<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * O diálogo nativo do navegador está vetado: sem controle visual, sem idioma e
 * sem cancelamento tratável. Este teste fecha a porta para que nenhum arquivo
 * novo volte a abrir.
 */
class NativeDialogTest extends TestCase
{
    public function test_nenhum_script_do_frontend_chama_dialogo_nativo(): void
    {
        $ofensas = [];

        foreach ($this->arquivos() as $arquivo) {
            $fonte = $this->semComentarios(file_get_contents($arquivo), str_ends_with($arquivo, '.blade.php'));

            if (preg_match('~(?<![\w$.])(?:window\.|globalThis\.)?(alert|confirm|prompt)\s*\(~', $fonte, $m)) {
                $ofensas[] = basename($arquivo).': '.$m[0];
            }
        }

        $this->assertSame([], $ofensas, 'Use Nf.toast(), Nf.confirm() ou Nf.prompt().');
    }

    private function arquivos(): array
    {
        $lista = [];

        foreach (['js', 'views'] as $pasta) {
            $it = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator(resource_path($pasta), \FilesystemIterator::SKIP_DOTS)
            );

            foreach ($it as $arquivo) {
                if (preg_match('!\.(js|blade\.php)$!', $arquivo->getFilename())) {
                    $lista[] = $arquivo->getPathname();
                }
            }
        }

        $this->assertNotEmpty($lista, 'Nada foi encontrado para inspecionar.');

        return $lista;
    }

    private function semComentarios(string $conteudo, bool $blade): string
    {
        if ($blade) {
            return preg_replace('!\{\{--.*?--\}\}!s', '', $conteudo);
        }

        $conteudo = preg_replace('!/\*.*?\*/!s', '', $conteudo);

        return preg_replace('!//[^\n]*!', '', $conteudo);
    }
}
