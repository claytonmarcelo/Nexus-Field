<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Support\Export;
use App\Support\ListFilters;
use App\Support\Relatorio;
use App\Support\StatusCatalog;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Os fechamentos de período da empresa.
 *
 * Nenhuma conta é refeita aqui. O controller escolhe qual relatório a rota pediu,
 * confere a permissão do módulo que ele resume e entrega o fechado que saiu do
 * MySQL: a tela fatia a página, o CSV exporta o inteiro, e as duas leituras vêm da
 * mesma consulta — fechado que discorda da própria exportação não fecha nada.
 *
 * **`reports.view` não basta.** O relatório de financeiro resume a carteira, o de
 * operação resume as ordens, o de chamados a mesa de atendimento e o de estoque o
 * livro-caixa. Cada tela pede também a permissão de leitura do módulo que resume:
 * um papel com `reports.view` e sem `financial.view` recebe 403 na rota de
 * financeiro, e o cartão dele não aparece no hub. É o mesmo degrau que o painel já
 * usa por bloco, aplicado a uma tela inteira.
 *
 * **O período é resolvido antes de consultar.** `inicio` e `fim` vêm da query
 * string, entram pela régua de `Relatorio::janela()` (padrão, lado faltante,
 * inversão e teto) e o aviso de corte aparece na tela. Sem o teto, um link com
 * datas de dez anos faria da agregação a consulta mais cara do sistema. No CSV, a
 * janela vai no nome do arquivo: exportado sem período escrito é arquivo que
 * ninguém sabe quando valeu.
 */
class ReportController extends Controller
{
    public function index(Request $request): View
    {
        $relatorio = new Relatorio($request, $request->user());

        return view('reports.index', [
            'janela' => $relatorio->janela(),
            'fechamento' => $relatorio->fechamento(),
            'disponiveis' => $relatorio->disponiveis(),
            'filtrosAtivos' => ListFilters::ativos($request, ['inicio', 'fim']),
        ]);
    }

    public function financeiro(Request $request): View
    {
        $this->garantirLeituraDoModulo($request, 'financeiro');
        $relatorio = new Relatorio($request, $request->user());
        $fechado = $relatorio->financeiro();

        return $this->tela($request, $relatorio, 'financeiro', $fechado, [
            'tipos' => StatusCatalog::options('financial_type'),
            'metodos' => $fechado['metodos'],
        ]);
    }

    public function operacao(Request $request): View
    {
        $this->garantirLeituraDoModulo($request, 'operacao');
        $relatorio = new Relatorio($request, $request->user());

        return $this->tela($request, $relatorio, 'operacao', $relatorio->operacao());
    }

    public function chamados(Request $request): View
    {
        $this->garantirLeituraDoModulo($request, 'chamados');
        $relatorio = new Relatorio($request, $request->user());
        $fechado = $relatorio->chamados();

        return $this->tela($request, $relatorio, 'chamados', $fechado, [
            'angulos' => Relatorio::ANGULOS,
            'angulo' => $fechado['angulo'],
        ]);
    }

    public function estoque(Request $request): View
    {
        $this->garantirLeituraDoModulo($request, 'estoque');
        $relatorio = new Relatorio($request, $request->user());
        $fechado = $relatorio->estoque();

        return $this->tela($request, $relatorio, 'estoque', $fechado, [
            'reposicao' => $fechado['reposicao'],
        ]);
    }

    /**
     * O CSV do período, nas mesmas linhas e na mesma ordem da tela.
     *
     * Exportar é `reports.export`, um degrau acima de ler: o arquivo sai da empresa
     * e vai para uma planilha que alguém envia por mensagem. O recorte é o mesmo da
     * tela porque é ele que a pessoa está olhando quando clica.
     */
    public function export(Request $request, string $relatorio): StreamedResponse
    {
        abort_unless(Relatorio::chaveValida($relatorio), 404, 'Este relatório não existe.');
        $this->garantirLeituraDoModulo($request, $relatorio);

        $consultado = new Relatorio($request, $request->user());
        $fechado = match ($relatorio) {
            'operacao' => $consultado->operacao(),
            'chamados' => $consultado->chamados(),
            'estoque' => $consultado->estoque(),
            default => $consultado->financeiro(),
        };
        $janela = $consultado->janela();

        return Export::csv(
            Relatorio::CATALOGO[$relatorio]['arquivo'].'-'.$janela['inicio']->format('Y-m-d').'-'.$janela['fim']->format('Y-m-d'),
            $consultado->cabecalho($relatorio),
            $consultado->linhasParaExportar($fechado['linhas'], $relatorio),
        );
    }

    /**
     * A mesma tela, montada a partir do fechado: `linhas` fatiadas para a página,
     * `totais` do período inteiro e a navegação entre os relatórios que a conta
     * pode abrir. Nada aqui recalcula número — só repassa o que saiu do banco. O
     * que é específico de um relatório (as formas de pagamento do financeiro, a
     * reposição do estoque) chega por `$extras`, porque a tela é quem decide
     * desenhá-lo e a agregação é quem já o calculou.
     *
     * @param  array<string, mixed>  $fechado
     * @param  array<string, mixed>  $extras
     */
    private function tela(Request $request, Relatorio $relatorio, string $chave, array $fechado, array $extras = []): View
    {
        return view(
            'reports.'.$chave,
            [
                'relatorio' => Relatorio::CATALOGO[$chave],
                'disponiveis' => $relatorio->disponiveis(),
                'janela' => $relatorio->janela(),
                'linhas' => $relatorio->paginar($fechado['linhas'], route('reports.'.$chave)),
                'totais' => $fechado['totais'],
                'filtrosAtivos' => ListFilters::ativos($request, $this->filtrosDaChave($chave)),
            ] + $extras
        );
    }

    /**
     * Ler o fechado de um módulo exige ler o módulo. A mensagem diz qual permissão
     * falta, porque 403 genérico manda a pessoa pedir ao administrador um degrau
     * que ela talvez já tenha.
     */
    private function garantirLeituraDoModulo(Request $request, string $chave): void
    {
        $modulo = Relatorio::CATALOGO[$chave]['modulo'];

        abort_unless(
            $request->user()->hasPermission($modulo),
            403,
            'Este relatório resume '.Relatorio::CATALOGO[$chave]['modulo_rotulo']
                .' e a permissão '.$modulo.' não está no seu papel.'
        );
    }

    /** @return array<int, string> */
    private function filtrosDaChave(string $chave): array
    {
        return match ($chave) {
            'financeiro' => ['inicio', 'fim', 'tipo'],
            'chamados' => ['inicio', 'fim', 'angulo'],
            default => ['inicio', 'fim'],
        };
    }
}
