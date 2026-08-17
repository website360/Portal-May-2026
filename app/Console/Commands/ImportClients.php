<?php

namespace App\Console\Commands;

use App\Models\Client;
use App\Models\Domain;
use App\Models\Invoice;
use App\Models\Project;
use App\Models\Task;
use App\Models\Transaction;
use App\Support\Encoding;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Importa a carteira de clientes exportada do sistema antigo.
 *
 * Feito para carga única: lê o CSV, faz o de-para, e pula quem já existe em vez
 * de duplicar. Rodar duas vezes não estraga nada.
 */
class ImportClients extends Command
{
    protected $signature = 'clientes:importar
        {arquivo : Caminho do CSV exportado do sistema antigo}
        {--dry-run : Mostra o que aconteceria, sem gravar nada}
        {--atualizar : Atualiza quem já existe em vez de pular}
        {--limpar : Apaga os clientes que não estão no arquivo}
        {--force : Não pede confirmação para apagar}';

    protected $description = 'Importa clientes de um CSV do sistema antigo';

    /** Colunas mínimas para o arquivo ser reconhecido. */
    private const REQUIRED_COLUMNS = ['client_type', 'cpf_cnpj', 'email'];

    public function handle(): int
    {
        $path = $this->argument('arquivo');

        if (! is_readable($path)) {
            $this->error("Não consegui ler o arquivo: {$path}");

            return self::FAILURE;
        }

        $rows = $this->readCsv($path);

        if ($rows === null) {
            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $update = (bool) $this->option('atualizar');

        $created = 0;
        $updated = 0;
        $skipped = [];
        $invalid = [];
        $review = [];
        $seenDocuments = [];
        $keep = [];

        foreach ($rows as $line => $row) {
            $data = $this->toClient($row);

            if ($data['name'] === null) {
                $invalid[] = [$line, 'sem nome', $row['email'] ?? '—'];

                continue;
            }

            $document = $data['document'];

            // Documento repetido dentro do próprio arquivo também é conflito:
            // a coluna é única, e o segundo insert falharia no meio da carga.
            if ($document !== null && isset($seenDocuments[$document])) {
                $skipped[] = [$line, $data['name'], "repetido no arquivo (linha {$seenDocuments[$document]})"];

                continue;
            }

            if ($document !== null) {
                $seenDocuments[$document] = $line;
            }

            $existing = $document === null
                ? Client::where('name', $data['name'])->first()
                : Client::where('document', $document)->first();

            if ($existing) {
                // Quem veio no arquivo fica, tenha sido atualizado ou pulado.
                $keep[] = $existing->id;

                if (! $update) {
                    $skipped[] = [$line, $data['name'], 'já cadastrado'];

                    continue;
                }

                if (! $dryRun) {
                    $existing->update($data);
                }

                $updated++;
            } else {
                if (! $dryRun) {
                    $keep[] = Client::create($data)->id;
                }

                $created++;
            }

            if ($this->looksInternal($data, $row)) {
                $review[] = [$line, $data['name'], $data['email'] ?? '—'];
            }
        }

        $this->report($rows, $created, $updated, $skipped, $invalid, $review, $dryRun);

        if ($this->option('limpar')) {
            $this->purge($keep, $dryRun);
        }

        return self::SUCCESS;
    }

    /**
     * Apaga quem não veio no arquivo — o CSV passa a ser a verdade.
     *
     * @param  array<int, int>  $keep
     */
    private function purge(array $keep, bool $dryRun): void
    {
        $doomed = Client::whereNotIn('id', $keep ?: [0]);
        $total = $doomed->count();

        $this->newLine();

        if ($total === 0) {
            $this->line('Nada a limpar: todo mundo no banco veio do arquivo.');

            return;
        }

        $ids = $doomed->pluck('id');

        // Apagar cliente derruba junto o que depende dele. Melhor dizer o
        // tamanho do estrago antes, não depois.
        $fallout = [
            'domínios' => Domain::whereIn('client_id', $ids)->count(),
            'projetos' => Project::whereIn('client_id', $ids)->count(),
            'faturas' => Invoice::whereIn('client_id', $ids)->count(),
            'tarefas' => Task::whereIn('client_id', $ids)->count(),
        ];

        $this->warn("Limpeza: {$total} clientes fora do arquivo.");

        foreach ($fallout as $label => $count) {
            if ($count > 0) {
                $this->line("  vão junto: {$count} {$label}");
            }
        }

        // Lançamentos não são apagados: a coluna é anulável, então eles ficam
        // órfãos em vez de sumirem. Vale avisar.
        $orphans = Transaction::whereIn('client_id', $ids)->count();

        if ($orphans > 0) {
            $this->line("  ficam sem cliente: {$orphans} lançamentos financeiros");
        }

        if ($dryRun) {
            $this->newLine();
            $this->warn('SIMULAÇÃO — ninguém foi apagado.');

            return;
        }

        if (! $this->option('force') && ! $this->confirm('Apagar?', false)) {
            $this->line('Limpeza cancelada.');

            return;
        }

        Client::whereIn('id', $ids)->delete();

        $this->newLine();
        $this->info("{$total} clientes apagados.");
    }

    /**
     * @return array<int, array<string, string|null>>|null
     */
    private function readCsv(string $path): ?array
    {
        $handle = fopen($path, 'r');
        $header = fgetcsv($handle);

        if ($header === false) {
            $this->error('O arquivo está vazio.');
            fclose($handle);

            return null;
        }

        // Remove o BOM que o Excel costuma colar na primeira coluna.
        $header[0] = preg_replace('/^\x{FEFF}/u', '', $header[0]);
        $header = array_map(fn ($column) => trim((string) $column), $header);

        $missing = array_diff(self::REQUIRED_COLUMNS, $header);

        if ($missing !== []) {
            $this->error('Não parece a exportação certa. Faltam as colunas: '.implode(', ', $missing));
            $this->line('Colunas encontradas: '.implode(', ', $header));
            fclose($handle);

            return null;
        }

        $rows = [];
        $line = 1;

        while (($values = fgetcsv($handle)) !== false) {
            $line++;

            if ($values === [null] || $values === []) {
                continue;
            }

            $rows[$line] = array_combine($header, array_pad(array_slice($values, 0, count($header)), count($header), null));
        }

        fclose($handle);

        return $rows;
    }

    /**
     * @param  array<string, string|null>  $row
     * @return array<string, mixed>
     */
    private function toClient(array $row): array
    {
        $isCompany = $this->value($row, 'client_type') === 'company';

        return [
            'type' => $isCompany ? Client::TYPE_COMPANY : Client::TYPE_PERSON,
            // Cada tipo guarda o nome numa coluna própria no sistema antigo.
            'name' => $this->value($row, $isCompany ? 'company_name' : 'full_name')
                ?? $this->value($row, $isCompany ? 'full_name' : 'company_name'),
            /*
             * `responsible_name` tem nome de contato mas guarda marca: "Inove-se",
             * "Agência May" — e, nas pessoas físicas, "Casa Mui", "By Artees".
             * É nome fantasia, não pessoa, nos dois tipos.
             */
            'trade_name' => $this->value($row, 'responsible_name'),
            'document' => $this->value($row, 'cpf_cnpj'),
            'email' => $this->value($row, 'email'),
            'phone' => $this->value($row, 'phone'),
            'status' => $this->value($row, 'is_active') === 'false' ? Client::STATUS_INACTIVE : Client::STATUS_ACTIVE,

            'zip_code' => $this->value($row, 'address_cep'),
            'street' => $this->value($row, 'address_street'),
            'number' => $this->value($row, 'address_number'),
            'complement' => $this->value($row, 'address_complement'),
            'district' => $this->value($row, 'address_neighborhood'),
            'city' => $this->value($row, 'address_city'),
            'state' => $this->value($row, 'address_state'),

            // Sem "cliente desde" na origem; a data do cadastro antigo é o
            // melhor que existe e é melhor que deixar vazio.
            'started_at' => $this->date($row, 'created_at'),
        ];
    }

    /**
     * @param  array<string, string|null>  $row
     */
    private function value(array $row, string $column): ?string
    {
        $value = trim((string) ($row[$column] ?? ''));

        // O export grava ausência como a palavra "null", não como vazio.
        if ($value === '' || strcasecmp($value, 'null') === 0) {
            return null;
        }

        // Alguns nomes vêm com espaço duplicado no meio.
        return preg_replace('/\s+/u', ' ', Encoding::repairMojibake($value));
    }

    /**
     * @param  array<string, string|null>  $row
     */
    private function date(array $row, string $column): ?string
    {
        $value = $this->value($row, $column);

        if ($value === null) {
            return null;
        }

        try {
            return Carbon::parse($value)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Registro interno ou de teste: entra, mas vale conferir depois.
     *
     * @param  array<string, mixed>  $data
     * @param  array<string, string|null>  $row
     */
    private function looksInternal(array $data, array $row): bool
    {
        $email = (string) ($data['email'] ?? '');

        return $data['document'] === null
            || str_contains($email, '@interno.local')
            || str_starts_with($email, 'teste@')
            || $this->value($row, 'phone') === '0000000000';
    }

    /**
     * @param  array<int, mixed>  $rows
     * @param  array<int, array<int, string>>  $skipped
     * @param  array<int, array<int, string>>  $invalid
     * @param  array<int, array<int, string>>  $review
     */
    private function report(array $rows, int $created, int $updated, array $skipped, array $invalid, array $review, bool $dryRun): void
    {
        $this->newLine();

        if ($dryRun) {
            $this->warn('SIMULAÇÃO — nada foi gravado.');
            $this->newLine();
        }

        $this->line("Linhas lidas .......... {$this->count($rows)}");
        $this->line(($dryRun ? 'Entrariam ............. ' : 'Criados ............... ').$created);

        if ($updated > 0) {
            $this->line(($dryRun ? 'Seriam atualizados .... ' : 'Atualizados ........... ').$updated);
        }

        if ($skipped !== []) {
            $this->line('Pulados ............... '.count($skipped));
            $this->newLine();
            $this->table(['Linha', 'Cliente', 'Motivo'], $skipped);
        }

        if ($invalid !== []) {
            $this->newLine();
            $this->error('Linhas sem nome, ignoradas:');
            $this->table(['Linha', 'Motivo', 'E-mail'], $invalid);
        }

        if ($review !== []) {
            $this->newLine();
            $this->warn('Parecem registro interno ou de teste — confira depois:');
            $this->table(['Linha', 'Cliente', 'E-mail'], $review);
        }
    }

    /**
     * @param  array<int, mixed>  $rows
     */
    private function count(array $rows): int
    {
        return count($rows);
    }
}
