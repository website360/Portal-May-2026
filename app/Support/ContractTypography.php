<?php

namespace App\Support;

/**
 * A tipografia e a arte do contrato em PDF.
 *
 * Reúne o que depende de arquivo em disco — fonte, timbrado, logo — e responde
 * com o que a folha de estilo precisa. Nada aqui é obrigatório: faltando o
 * arquivo, o contrato sai assim mesmo, com o que a biblioteca já traz.
 */
final class ContractTypography
{
    /** As imagens que a biblioteca de PDF sabe desenhar. */
    private const IMAGE_TYPES = [
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'gif' => 'image/gif',
    ];

    /** Os quatro pesos que um contrato usa, e o arquivo de cada um. */
    private const FACES = [
        'regular' => ['weight' => 'normal', 'style' => 'normal'],
        'bold' => ['weight' => 'bold', 'style' => 'normal'],
        'italic' => ['weight' => 'normal', 'style' => 'italic'],
        'bolditalic' => ['weight' => 'bold', 'style' => 'italic'],
    ];

    /**
     * A família a usar no corpo do texto.
     *
     * Só assume a fonte própria quando existe pelo menos o peso normal — uma
     * família declarada sem arquivo nenhum faria o texto cair numa fonte
     * qualquer do sistema, e o PDF sairia diferente em cada máquina.
     */
    public function family(): string
    {
        $name = (string) config('contratos.fonte.nome');

        return $this->fileFor('regular') === null ? '"DejaVu Sans", sans-serif' : "\"{$name}\", \"DejaVu Sans\", sans-serif";
    }

    /**
     * As regras @font-face dos arquivos que existirem.
     *
     * O caminho vai absoluto: a biblioteca resolve a partir do diretório de
     * trabalho, que muda conforme quem chamou — rota, comando ou teste.
     */
    public function fontFaces(): string
    {
        $name = (string) config('contratos.fonte.nome');
        $rules = [];

        foreach (self::FACES as $face => $css) {
            $file = $this->fileFor($face);

            if ($file === null) {
                continue;
            }

            $rules[] = sprintf(
                '@font-face { font-family: "%s"; font-weight: %s; font-style: %s; src: url("%s") format("truetype"); }',
                $name,
                $css['weight'],
                $css['style'],
                str_replace('\\', '/', $file)
            );
        }

        return implode("\n        ", $rules);
    }

    /**
     * Os pesos que faltam, para a tela poder avisar.
     *
     * Sem bold.ttf o negrito simplesmente não acontece — e um contrato sem
     * cláusula em negrito parece um rascunho.
     *
     * @return list<string>
     */
    public function missingFaces(): array
    {
        if ($this->fileFor('regular') === null) {
            return [];
        }

        return array_values(array_filter(
            array_keys(self::FACES),
            fn (string $face) => $this->fileFor($face) === null
        ));
    }

    /** O timbrado como data URI, ou null quando não há arquivo. */
    public function letterhead(): ?string
    {
        return $this->imageAt($this->scaledLetterhead());
    }

    /**
     * Uma cópia do timbrado no tamanho que a página usa, guardada em cache.
     *
     * A arte costuma vir em 300 dpi — o dobro do que a página precisa — e a
     * biblioteca redimensiona a imagem inteira uma vez por página. Num contrato
     * de sete páginas isso custa mais de três segundos; com a cópia reduzida,
     * pouco mais de um. A conversão acontece uma vez por arte, não por PDF.
     */
    private function scaledLetterhead(): ?string
    {
        $source = $this->letterheadPath();

        if ($source === null || ! function_exists('imagecreatefrompng')) {
            return $source;
        }

        $width = (int) config('contratos.timbrado_largura');
        $size = @getimagesize($source);

        // Já está no tamanho certo (ou menor): não há o que ganhar.
        if ($width < 1 || $size === false || $size[0] <= $width) {
            return $source;
        }

        // A chave muda quando o arquivo muda: trocar a arte invalida o cache.
        $cache = storage_path('app/contratos/cache/timbrado-'.md5($source.filemtime($source).filesize($source)).'.png');

        if (is_file($cache)) {
            return $cache;
        }

        return $this->writeScaled($source, $cache, $width, (int) round($width * $size[1] / $size[0])) ?? $source;
    }

    /**
     * Grava a cópia reduzida, ou devolve null se não conseguir.
     *
     * Falhar aqui é aceitável — perde-se a velocidade, não o contrato. Uma arte
     * enorme pode estourar a memória do PHP, e isso não pode impedir o PDF.
     */
    private function writeScaled(string $source, string $cache, int $width, int $height): ?string
    {
        try {
            // @: PNG exportado de editor costuma ter perfil de cor irregular, e
            // o aviso da biblioteca gráfica não impede a leitura.
            $image = @imagecreatefrompng($source);

            if ($image === false) {
                return null;
            }

            $scaled = imagescale($image, $width, $height);
            imagedestroy($image);

            if ($scaled === false) {
                return null;
            }

            imagealphablending($scaled, false);
            imagesavealpha($scaled, true);

            if (! is_dir(dirname($cache))) {
                mkdir(dirname($cache), 0755, true);
            }

            $ok = imagepng($scaled, $cache, 6);
            imagedestroy($scaled);

            return $ok ? $cache : null;
        } catch (\Throwable) {
            return null;
        }
    }

    /** A logo do cabeçalho desenhado — só usada sem timbrado. */
    public function logo(): ?string
    {
        return $this->imageAt($this->logoPath());
    }

    public function letterheadPath(): ?string
    {
        return $this->resolveImage((string) config('contratos.timbrado'));
    }

    public function logoPath(): ?string
    {
        return $this->resolveImage((string) config('contratos.logo'));
    }

    /**
     * O que a tela mostra sobre a arte e a tipografia.
     *
     * Existe porque isto falhava calado: sem retorno na tela, a única forma de
     * descobrir que o arquivo não foi encontrado era gerar um PDF e reparar que
     * o fundo estava branco.
     *
     * @return array<string, mixed>
     */
    public function status(): array
    {
        $letterhead = $this->letterheadPath();
        $regular = $this->fileFor('regular');

        return [
            'letterhead' => [
                'found' => $letterhead !== null,
                // Onde está, ou onde deveria estar — a mensagem serve para os dois casos.
                'path' => $this->relative($letterhead) ?? (string) config('contratos.timbrado'),
            ],
            'logo' => [
                'found' => $this->logoPath() !== null,
                'path' => $this->relative($this->logoPath()) ?? (string) config('contratos.logo'),
            ],
            'font' => [
                'found' => $regular !== null,
                'name' => $regular === null ? 'DejaVu Sans (padrão)' : (string) config('contratos.fonte.nome'),
                'folder' => (string) config('contratos.fonte.pasta'),
                'missing' => $this->missingFaces(),
            ],
            'extensions' => self::IMAGE_TYPES,
            'shadowing' => $this->shadowingFolders(),
        ];
    }

    /**
     * Pastas em public/ que escondem uma rota de mesmo nome.
     *
     * public/ é a raiz do site: uma pasta chamada "contratos" ali é encontrada
     * pelo servidor antes da rota, e /contratos passa a responder 404. É um
     * erro fácil de cometer — basta salvar o timbrado no lugar errado — e o
     * sintoma não aponta para a causa.
     *
     * @return list<string>
     */
    private function shadowingFolders(): array
    {
        $modules = array_keys(Permissions::MODULES);

        return array_values(array_filter(
            array_map('basename', glob(public_path('*'), GLOB_ONLYDIR) ?: []),
            fn (string $folder) => in_array($folder, $modules, true)
        ));
    }

    /**
     * @return array{topo: string, direita: string, base: string, esquerda: string}
     */
    public function margins(): array
    {
        return [
            'topo' => (string) config('contratos.margens.topo'),
            'direita' => (string) config('contratos.margens.direita'),
            'base' => (string) config('contratos.margens.base'),
            'esquerda' => (string) config('contratos.margens.esquerda'),
        ];
    }

    private function fileFor(string $face): ?string
    {
        $folder = base_path((string) config('contratos.fonte.pasta'));

        foreach (['ttf', 'otf'] as $extension) {
            $path = "{$folder}/{$face}.{$extension}";

            if (is_file($path)) {
                return $path;
            }
        }

        return null;
    }

    /**
     * Acha o arquivo aceitando qualquer extensão de imagem que sirva.
     *
     * O caminho configurado termina em .png, mas exigir exatamente essa
     * extensão faria um .jpg salvo ali ser ignorado sem dizer nada — e ninguém
     * adivinha que o problema é a extensão.
     */
    private function resolveImage(string $relative): ?string
    {
        $path = base_path($relative);

        if (is_file($path) && $this->mimeOf($path) !== null) {
            return $path;
        }

        $withoutExtension = preg_replace('/\.[^.\/\\\\]+$/', '', $path) ?? $path;

        foreach (self::IMAGE_TYPES as $extension => $mime) {
            foreach ([$extension, strtoupper($extension)] as $tried) {
                if (is_file("{$withoutExtension}.{$tried}")) {
                    return "{$withoutExtension}.{$tried}";
                }
            }
        }

        return null;
    }

    /**
     * Imagem embutida como data URI.
     *
     * Embutir em vez de referenciar evita depender do diretório de trabalho e
     * de permissão de leitura remota da biblioteca — o PDF sai igual de
     * qualquer origem.
     */
    private function imageAt(?string $path): ?string
    {
        if ($path === null) {
            return null;
        }

        $mime = $this->mimeOf($path);

        return $mime === null ? null : "data:{$mime};base64,".base64_encode((string) file_get_contents($path));
    }

    private function mimeOf(string $path): ?string
    {
        // SVG a biblioteca não rasteriza de forma confiável; melhor não tentar.
        return self::IMAGE_TYPES[strtolower(pathinfo($path, PATHINFO_EXTENSION))] ?? null;
    }

    /** Caminho relativo ao projeto, que é como a tela fala com quem usa. */
    private function relative(?string $path): ?string
    {
        if ($path === null) {
            return null;
        }

        return str_replace('\\', '/', str_replace(base_path().DIRECTORY_SEPARATOR, '', $path));
    }
}
