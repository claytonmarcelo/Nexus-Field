<?php

namespace App\Support;

use DOMComment;
use DOMDocument;
use DOMElement;

/**
 * Texto rico que volta para a tela precisa ser seguro antes de virar bytes no
 * banco. O editor de chamados (Summernote) devolve HTML, e esse HTML é escrito
 * por outra pessoa — às vezes pela conta de cliente. Renderizar com `{!! !!}` um
 * texto que não passou por aqui seria XSS estocado: a tela executaria no
 * navegador de quem lê, não no de quem escreveu.
 *
 * A regra é lista fechada, não lista de proibições: o que não está permitido
 * desaparece, e a palavra do autor fica. Desembrulhar um rótulo desconhecido é
 * melhor que apagar o parágrafo inteiro.
 */
class TextoSeguro
{
    /**
     * @var array<string, array<int, string>> tag => atributos aceitos
     */
    private const PERMITIDOS = [
        'p' => [],
        'br' => [],
        'strong' => [],
        'b' => [],
        'em' => [],
        'i' => [],
        'u' => [],
        's' => [],
        'ul' => [],
        'ol' => [],
        'li' => [],
        'blockquote' => [],
        'code' => [],
        'pre' => [],
        'h3' => [],
        'h4' => [],
        'h5' => [],
        'div' => [],
        'span' => [],
        'a' => ['href', 'title', 'rel'],
    ];

    /** @var array<int, string> tags que somem junto com o que têm dentro. */
    private const PROIBIDOS_COM_CONTEUDO = [
        'script', 'style', 'iframe', 'object', 'embed', 'form', 'input', 'textarea', 'button',
        'select', 'option', 'link', 'meta', 'svg', 'math', 'noscript', 'template', 'img',
        'video', 'audio', 'canvas', 'applet', 'frame', 'frameset', 'marquee', 'title', 'head',
        'body', 'html',
    ];

    /** @var array<int, string> esquemas de link que fazem sentido num chamado. */
    private const ESQUEMAS = ['http', 'https', 'mailto', 'tel'];

    public static function sanitizar(?string $bruto): ?string
    {
        if ($bruto === null || trim($bruto) === '') {
            return null;
        }

        $documento = new DOMDocument;
        $anterior = libxml_use_internal_errors(true);

        // O prefixo com a declaração de codificação é o que faz o parser HTML do
        // libxml ler UTF-8 em vez de supor CP1252; ele não chega à saída, porque só
        // serializamos os filhos do nosso próprio invólucro.
        $documento->loadHTML(
            '<?xml encoding="utf-8" ?><div>'.$bruto.'</div>',
            LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_NONET,
        );

        libxml_clear_errors();
        libxml_use_internal_errors($anterior);

        $raiz = $documento->getElementsByTagName('div')->item(0);

        if ($raiz === null) {
            return null;
        }

        static::poda($raiz, $documento);

        $saida = '';

        foreach ($raiz->childNodes as $filho) {
            $saida .= $documento->saveHTML($filho);
        }

        $saida = trim(str_replace(["\r\n", "\r"], "\n", $saida));

        return static::textoPlano($saida) === '' ? null : $saida;
    }

    /**
     * O texto que se lê, sem rótulo nenhum: é o que a exportação coloca na célula
     * do CSV e o que a validação mede quando precisa de conteúdo de verdade.
     */
    public static function textoPlano(?string $conteudo): string
    {
        if ($conteudo === null || trim($conteudo) === '') {
            return '';
        }

        $texto = html_entity_decode(strip_tags($conteudo), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim((string) preg_replace('/\s+/u', ' ', $texto));
    }

    private static function poda(DOMElement $no, DOMDocument $documento): void
    {
        foreach (iterator_to_array($no->childNodes) as $filho) {
            if ($filho instanceof DOMComment) {
                $no->removeChild($filho);

                continue;
            }

            if (! $filho instanceof DOMElement) {
                continue;
            }

            $tag = mb_strtolower($filho->tagName);

            if (in_array($tag, self::PROIBIDOS_COM_CONTEUDO, true)) {
                $no->removeChild($filho);

                continue;
            }

            static::poda($filho, $documento);

            if (! array_key_exists($tag, self::PERMITIDOS)) {
                // Desembrulha: mantém a palavra, descarta o rótulo que a tela não
                // sabe desenhar.
                while ($filho->firstChild !== null) {
                    $no->insertBefore($filho->firstChild, $filho);
                }

                $no->removeChild($filho);

                continue;
            }

            static::atributos($filho, self::PERMITIDOS[$tag], $tag);
        }
    }

    /**
     * @param  array<int, string>  $aceitos
     */
    private static function atributos(DOMElement $no, array $aceitos, string $tag): void
    {
        foreach (iterator_to_array($no->attributes) as $atributo) {
            if (! in_array(mb_strtolower($atributo->name), $aceitos, true)) {
                $no->removeAttribute($atributo->name);
            }
        }

        if ($tag !== 'a') {
            return;
        }

        $href = trim($no->getAttribute('href'));

        if (! static::hrefPermitido($href)) {
            $no->removeAttribute('href');
        }

        // Link que sobrevive vira rota de saída do painel: sem `noopener`, a
        // página aberta continua com acesso à janela que a chamou.
        $no->setAttribute('rel', 'noopener nofollow');
    }

    private static function hrefPermitido(string $href): bool
    {
        if ($href === '' || $href[0] === '#') {
            return false;
        }

        if (preg_match('/^([a-z][a-z0-9+.\-]*):/i', $href, $correspondencia) === 1) {
            return in_array(mb_strtolower($correspondencia[1]), self::ESQUEMAS, true);
        }

        // Sem esquema (`example.com`, um caminho relativo) não é link confiável: o
        // navegador resolveria contra o painel, e o texto continua legível sem ele.
        return false;
    }
}
