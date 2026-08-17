<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>{{ $contract->number }} — {{ $contract->title }}</title>

    <style>
        {{-- As fontes da agência, quando os arquivos existem. --}}
        {!! $fontFaces !!}

        @page {
            margin: {{ $margins['topo'] }} {{ $margins['direita'] }} {{ $margins['base'] }} {{ $margins['esquerda'] }};
        }

        body {
            font-family: {!! $fontFamily !!};
            font-size: {{ $fontSize }};
            line-height: {{ $lineHeight }};
            color: #1a1a1a;
        }

        /*
            O timbrado ocupa a folha inteira e é fixo, que é o que faz a
            biblioteca repeti-lo em todas as páginas. As coordenadas negativas
            levam a arte até a borda do papel: um elemento fixo é posicionado a
            partir da área útil, e não da página.

            z-index: elemento fixo é pintado depois do conteúdo; sem mandá-lo
            para trás, a arte cobre o texto e a página sai em branco.
        */
        .timbrado {
            position: fixed;
            top: -{{ $margins['topo'] }}; left: -{{ $margins['esquerda'] }};
            width: 210mm; height: 297mm;
            z-index: -1;
        }
        .timbrado img { width: 210mm; height: 297mm; }

        header {
            position: fixed;
            top: -26mm; left: 0; right: 0;
            border-bottom: 2px solid #111;
            padding-bottom: 6px;
        }
        header img { height: 34px; }
        header .marca { font-size: 13pt; font-weight: bold; letter-spacing: -.02em; }
        header .regua { height: 3px; background: #F5B700; width: 34%; margin-top: 2px; }

        footer {
            position: fixed;
            bottom: -16mm; left: 0; right: 0;
            font-size: 7.5pt;
            color: #9a9a9a;
        }

        h1 { font-size: 12.5pt; font-weight: bold; text-align: center; margin: 0 0 20px; text-transform: uppercase; }
        h2 { font-size: 10.5pt; font-weight: bold; margin: 20px 0 8px; text-transform: uppercase; }
        h3, h4 { font-size: 10.5pt; font-weight: bold; margin: 15px 0 5px; }

        p { margin: 0 0 10px; text-align: justify; }
        strong, b { font-weight: bold; }
        em, i { font-style: italic; }

        ul, ol { margin: 0 0 10px; padding-left: 20px; }
        li { margin-bottom: 5px; text-align: justify; }
        hr { border: 0; border-top: 1px solid #ccc; margin: 18px 0; }

        table { width: 100%; border-collapse: collapse; margin: 12px 0 16px; font-size: 9.5pt; }
        th, td { border: 1px solid #333; padding: 5px 8px; text-align: left; }
        th { font-weight: bold; }

        /* Bloco de assinaturas, quando o modelo pede: sem flex, que a biblioteca
           não suporta — duas colunas de tabela resolvem. */
        .assinaturas { width: 100%; margin-top: 48px; border: 0; }
        .assinaturas td { width: 50%; padding-top: 34px; text-align: center; font-size: 9pt; border: 0; }
        .assinaturas .linha { border-top: 1px solid #1a1a1a; padding-top: 5px; }
        .assinaturas strong { display: block; }
        .assinaturas span { color: #666; font-size: 8.5pt; display: block; }
    </style>
</head>
<body>
    @if ($letterhead)
        <div class="timbrado"><img src="{{ $letterhead }}" alt=""></div>
    @endif

    @if ($drawHeader)
        <header>
            @if ($logo)
                <img src="{{ $logo }}" alt="{{ $agencyBrand }}">
            @else
                <div class="marca">{{ $agencyBrand }}</div>
            @endif
            <div class="regua"></div>
        </header>
    @endif

    <footer>{{ $contract->number }} · {{ $contract->client->display_name }} · {{ $contract->title }}</footer>

    <h1>{{ $contract->title }}</h1>

    {!! $body !!}

    @if ($signatures)
        <table class="assinaturas">
            <tr>
                <td>
                    <div class="linha">
                        <strong>{{ $agencyBrand }}</strong>
                        <span>{{ $agencyDocument }}</span>
                        <span>Contratada</span>
                    </div>
                </td>
                <td>
                    <div class="linha">
                        <strong>{{ $contract->client->name }}</strong>
                        <span>{{ $clientDocument }}</span>
                        <span>Contratante</span>
                    </div>
                </td>
            </tr>
        </table>
    @endif
</body>
</html>
