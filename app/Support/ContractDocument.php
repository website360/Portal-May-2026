<?php

namespace App\Support;

use App\Models\Contract;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;

/**
 * O PDF de um contrato.
 *
 * Sai do texto guardado no contrato, não do modelo: o modelo pode ser editado
 * depois, e reimprimir um contrato assinado tem de devolver o mesmo papel.
 */
final class ContractDocument
{
    private ContractTypography $art;

    public function __construct(private readonly Contract $contract)
    {
        $this->art = new ContractTypography;
    }

    /** Nome do arquivo para download: "MAY-2026-0001 - Box Locadora.pdf". */
    public function filename(): string
    {
        $client = preg_replace('/[^\p{L}\p{N} \-]/u', '', $this->contract->client->display_name);

        return trim("{$this->contract->number} - {$client}").'.pdf';
    }

    public function html(): string
    {
        $letterhead = $this->art->letterhead();

        return view('contratos.pdf', [
            'contract' => $this->contract,
            'body' => ContractMarkup::toHtml($this->contract->body),
            'agencyDocument' => Documents::format(config('contratos.agencia.documento')),
            'clientDocument' => Documents::format($this->contract->client->document),
            'agencyBrand' => config('contratos.agencia.nome'),
            'letterhead' => $letterhead,
            // Com timbrado, a marca e a régua já vêm na arte: desenhar por cima
            // duplicaria o cabeçalho.
            'logo' => $letterhead === null ? $this->art->logo() : null,
            'drawHeader' => $letterhead === null,
            'fontFaces' => $this->art->fontFaces(),
            'fontFamily' => $this->art->family(),
            'fontSize' => config('contratos.fonte.tamanho'),
            'lineHeight' => config('contratos.fonte.entrelinha'),
            'margins' => $this->art->margins(),
            // Quem assina pela Clicksign não quer linha de assinatura no papel;
            // quem imprime, quer. É escolha de cada modelo.
            'signatures' => (bool) $this->contract->template?->with_signatures,
        ])->render();
    }

    /**
     * Para baixar: o navegador salva o arquivo.
     *
     * Aqui vale otimizar o tamanho — é o arquivo que vai por e-mail e sobe para
     * a assinatura eletrônica.
     */
    public function stream(): Response
    {
        return $this->pdf(compact: true)->download($this->filename());
    }

    /**
     * Para ver na tela: o navegador abre no próprio visualizador.
     *
     * É o que permite conferir o contrato como ele fica — paginação, timbrado,
     * tipografia — sem precisar salvar e baixar antes. Aqui vale o contrário:
     * o que incomoda é a espera, não o tamanho, que não sai da máquina.
     */
    public function inline(): Response
    {
        return $this->pdf(compact: false)->stream($this->filename());
    }

    /**
     * @param  bool  $compact  embute só os caracteres usados: deixa o arquivo
     *                         quase 90% menor, e a geração umas sete vezes mais
     *                         lenta. Vale para baixar, não para conferir.
     */
    private function pdf(bool $compact): \Barryvdh\DomPDF\PDF
    {
        return Pdf::loadHTML($this->html())
            ->setOption([
                /*
                 * chroot no projeto: a biblioteca só lê arquivos daqui para
                 * baixo, que é onde ficam as fontes. Sem isso ela recusa o
                 * @font-face e o texto cai numa fonte qualquer, sem aviso.
                 */
                'chroot' => base_path(),
                'isRemoteEnabled' => true,
                'defaultFont' => 'DejaVu Sans',
                'isFontSubsettingEnabled' => $compact,
                'fontDir' => $this->fontCache(),
                'fontCache' => $this->fontCache(),
            ])
            ->setPaper('a4');
    }

    /**
     * Onde a biblioteca guarda as fontes que converteu.
     *
     * Criado sob demanda: a pasta não vem no repositório, e sem ela a conversão
     * falha com um aviso de arquivo inexistente que não diz o que fazer.
     */
    private function fontCache(): string
    {
        $path = storage_path('fonts');

        if (! is_dir($path)) {
            mkdir($path, 0755, true);
        }

        return $path;
    }
}
