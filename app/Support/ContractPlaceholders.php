<?php

namespace App\Support;

use App\Models\Client;
use App\Models\Contract;
use Illuminate\Support\Carbon;

/**
 * Os marcadores que um modelo de contrato pode usar.
 *
 * A ideia central do gerador: o modelo declara o que precisa. O que o sistema
 * sabe responder — nome do cliente, CNPJ, endereço — ele preenche sozinho; o
 * que ele não conhece vira um campo do formulário na hora de gerar. Assim
 * escrever um contrato novo não exige mexer em código.
 */
final class ContractPlaceholders
{
    /** Marcador no texto: {{ cliente.nome }}, com ou sem espaços. */
    private const PATTERN = '/\{\{\s*([a-z0-9_.]+)\s*\}\}/i';

    /**
     * O que o sistema preenche sozinho, agrupado para a tela de ajuda.
     *
     * @var array<string, array<string, string>>
     */
    public const CATALOG = [
        'Cliente' => [
            'cliente.nome' => 'Nome ou marca pela qual o cliente é chamado',
            'cliente.razao_social' => 'Razão social ou nome civil completo',
            'cliente.documento' => 'CNPJ ou CPF, já formatado',
            'cliente.email' => 'E-mail principal',
            'cliente.telefone' => 'Telefone principal',
            'cliente.responsavel' => 'Nome de quem responde pelo cliente',
            'cliente.cargo_responsavel' => 'Cargo de quem responde',
            'cliente.representante' => 'Quem assina pelo cliente',
            'cliente.representante_cargo' => 'Cargo de quem assina (sócio administrador…)',
            'cliente.representante_cpf' => 'CPF de quem assina',
            'cliente.qualificacao' => 'O bloco inteiro: razão social, CNPJ, representante e endereço',
            'cliente.endereco' => 'Endereço completo em uma linha',
            'cliente.logradouro' => 'Rua e número',
            'cliente.bairro' => 'Bairro',
            'cliente.cidade' => 'Cidade',
            'cliente.estado' => 'UF',
            'cliente.cep' => 'CEP',
        ],
        'Contrato' => [
            'contrato.numero' => 'Número do contrato',
            'contrato.servico' => 'Serviço contratado',
            'contrato.valor' => 'Valor, escrito como R$ 1.234,56',
            'contrato.valor_extenso' => 'Valor por extenso',
            'contrato.inicio' => 'Início da vigência',
            'contrato.fim' => 'Fim da vigência, ou “prazo indeterminado”',
        ],
        'Contratada' => [
            'agencia.nome' => 'Nome da agência',
            'agencia.documento' => 'CNPJ da agência',
            'agencia.endereco' => 'Endereço da agência',
            'agencia.representante' => 'Quem assina pela agência',
            'agencia.representante_cpf' => 'CPF de quem assina',
            'agencia.representante_rg' => 'RG de quem assina',
            'agencia.qualificacao' => 'O bloco inteiro da contratada, pronto',
        ],
        'Data' => [
            'data.hoje' => 'Data de hoje, 13/08/2026',
            'data.extenso' => 'Data de hoje por extenso',
            'data.cidade_data' => 'Cidade da agência e data por extenso',
        ],
    ];

    /**
     * Todos os marcadores usados num texto, na ordem em que aparecem.
     *
     * @return list<string>
     */
    public static function usedIn(?string $body): array
    {
        if (blank($body)) {
            return [];
        }

        preg_match_all(self::PATTERN, $body, $matches);

        return array_values(array_unique(array_map('strtolower', $matches[1] ?? [])));
    }

    /** @return list<string> */
    public static function known(): array
    {
        return array_merge(...array_map('array_keys', array_values(self::CATALOG)));
    }

    /**
     * Os marcadores do texto que o sistema não sabe preencher.
     *
     * São eles que viram campos do formulário — é assim que um modelo pede
     * "prazo de aviso prévio" sem que ninguém precise programar nada.
     *
     * @return list<string>
     */
    public static function customIn(?string $body): array
    {
        return array_values(array_diff(self::usedIn($body), self::known()));
    }

    /**
     * O rótulo que a tela mostra para um marcador livre:
     * "prazo_aviso_previo" vira "Prazo aviso previo".
     */
    public static function labelFor(string $placeholder): string
    {
        $label = trim(str_replace(['_', '.'], ' ', $placeholder));

        // mb_ucfirst só existe no PHP 8.4; aqui ainda é 8.3.
        return mb_strtoupper(mb_substr($label, 0, 1)).mb_substr($label, 1);
    }

    /**
     * Todos os valores conhecidos para um contrato.
     *
     * @return array<string, string>
     */
    public static function valuesFor(Contract $contract): array
    {
        $client = $contract->client;

        return [
            ...self::clientValues($client),
            'contrato.numero' => $contract->number,
            'contrato.servico' => $contract->service,
            'contrato.valor' => $contract->value === null ? '—' : 'R$ '.number_format((float) $contract->value, 2, ',', '.'),
            'contrato.valor_extenso' => $contract->value === null ? '—' : Money::inWords((float) $contract->value),
            'contrato.inicio' => $contract->starts_at->format('d/m/Y'),
            'contrato.fim' => $contract->ends_at?->format('d/m/Y') ?? 'prazo indeterminado',
            ...self::agencyValues(),
            ...self::dateValues(),
        ];
    }

    /**
     * @return array<string, string>
     */
    private static function clientValues(Client $client): array
    {
        $street = trim(implode(', ', array_filter([$client->street, $client->number])));

        $address = implode(' — ', array_filter([
            trim(implode(', ', array_filter([$street, $client->complement]))),
            $client->district,
            trim(implode('/', array_filter([$client->city, $client->state]))),
            $client->zip_code ? "CEP {$client->zip_code}" : null,
        ]));

        /*
         * Quem assina é o representante legal; sem ele cadastrado, cai no
         * contato do dia a dia — é melhor sair o nome de alguém do que um
         * marcador cru no meio da qualificação das partes.
         */
        $representative = filled($client->representative_name) ? $client->representative_name : ($client->contact_name ?? '');
        $representativeRole = filled($client->representative_role) ? $client->representative_role : ($client->contact_role ?? '');
        $representativeDocument = Documents::format($client->representative_document);

        return [
            'cliente.nome' => $client->display_name,
            'cliente.razao_social' => $client->name,
            'cliente.documento' => Documents::format($client->document),
            'cliente.email' => $client->email ?? '',
            'cliente.telefone' => $client->phone ?? '',
            'cliente.responsavel' => $client->contact_name ?? '',
            'cliente.cargo_responsavel' => $client->contact_role ?? '',
            'cliente.representante' => $representative,
            'cliente.representante_cargo' => $representativeRole,
            'cliente.representante_cpf' => $representativeDocument,
            'cliente.qualificacao' => self::clientQualification(
                $client,
                $representative,
                $representativeRole,
                $representativeDocument,
                $address
            ),
            'cliente.endereco' => $address,
            'cliente.logradouro' => $street,
            'cliente.bairro' => $client->district ?? '',
            'cliente.cidade' => $client->city ?? '',
            'cliente.estado' => $client->state ?? '',
            'cliente.cep' => $client->zip_code ?? '',
        ];
    }

    /**
     * A qualificação da contratante, escrita como um contrato escreve.
     *
     * Existe como marcador único porque é o bloco que abre todo contrato e
     * nunca muda de forma — repetir os seis marcadores soltos em cada modelo
     * seria seis oportunidades de esquecer um.
     */
    private static function clientQualification(
        Client $client,
        string $representative,
        string $role,
        string $document,
        string $address
    ): string {
        $company = Documents::isCompany($client->document);

        $parts = [
            '**'.$client->name.'**',
            $company
                ? 'inscrita no CNPJ sob o nº '.Documents::format($client->document)
                : 'inscrito(a) no CPF/MF sob o nº '.Documents::format($client->document),
        ];

        // Pessoa física assina por si: não há representante a declarar.
        if ($company && filled($representative)) {
            $qualification = filled($role) ? "representada pelo(a) {$role} {$representative}" : "representada por {$representative}";

            if (filled($document)) {
                $qualification .= ", inscrito(a) no CPF/MF sob o nº {$document}";
            }

            $parts[] = $qualification;
        }

        if (filled($address)) {
            $parts[] = "com endereço à {$address}";
        }

        return implode(', ', array_filter($parts)).'.';
    }

    /**
     * @return array<string, string>
     */
    private static function agencyValues(): array
    {
        $name = config('contratos.agencia.nome');
        $document = Documents::format(config('contratos.agencia.documento'));
        $representative = config('contratos.agencia.representante');
        $cpf = Documents::format(config('contratos.agencia.representante_cpf'));
        $rg = config('contratos.agencia.representante_rg');
        $address = config('contratos.agencia.endereco');

        $qualification = array_filter([
            "**{$name}**",
            'pessoa jurídica de direito privado',
            filled($document) ? "inscrita no CNPJ/MF sob o nº {$document}" : null,
            filled($address) ? "com sede à {$address}" : null,
            filled($representative) ? "neste ato representada pelo sócio administrador {$representative}" : null,
            filled($rg) ? "portador da cédula de identidade RG nº {$rg}" : null,
            filled($cpf) ? "inscrito no CPF/MF sob o nº {$cpf}" : null,
        ]);

        return [
            'agencia.nome' => $name,
            'agencia.documento' => $document,
            'agencia.endereco' => $address,
            'agencia.representante' => $representative,
            'agencia.representante_cpf' => $cpf,
            'agencia.representante_rg' => $rg,
            'agencia.qualificacao' => implode(', ', $qualification).'.',
        ];
    }

    /**
     * @return array<string, string>
     */
    private static function dateValues(): array
    {
        $today = Carbon::today();
        $long = $today->translatedFormat('j \d\e F \d\e Y');

        return [
            'data.hoje' => $today->format('d/m/Y'),
            'data.extenso' => $long,
            'data.cidade_data' => config('contratos.agencia.cidade').", {$long}",
        ];
    }

    /**
     * Troca os marcadores pelos valores.
     *
     * Marcador sem valor sai como está, e não em branco: um contrato com
     * "{{prazo}}" escrito no meio denuncia o que faltou; um espaço vazio passa
     * despercebido e vai assinado assim.
     *
     * @param  array<string, string>  $values
     */
    public static function render(string $body, array $values): string
    {
        return preg_replace_callback(
            self::PATTERN,
            function (array $match) use ($values) {
                $key = strtolower($match[1]);

                return array_key_exists($key, $values) && filled($values[$key]) ? $values[$key] : $match[0];
            },
            $body
        ) ?? $body;
    }
}
