<?php

namespace App\Console\Commands;

use App\Models\Contract;
use App\Support\ContractExpiryAlert;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Avisa a agência dos contratos que se aproximam do fim.
 *
 * Roda todo dia. Dispara nos marcos de 30, 15, 7 e 1 dia antes do fim — como a
 * distância cai um dia por dia, cada marco acontece uma vez, sem repetir o aviso
 * todo dia. Um contrato encerrado, cancelado ou ainda em rascunho não entra.
 */
class AlertExpiringContracts extends Command
{
    /** Marcos de antecedência, em dias. */
    private const MILESTONES = [1, 7, 15, 30];

    protected $signature = 'contratos:avisar-vencimento
        {--dry-run : Mostra o que seria avisado, sem enviar}';

    protected $description = 'Avisa os administradores dos contratos que vencem em 30, 15, 7 ou 1 dia';

    public function handle(): int
    {
        $dates = collect(self::MILESTONES)->map(fn (int $days) => Carbon::today()->addDays($days)->toDateString());

        $contracts = Contract::query()
            ->whereIn('ends_at', $dates)
            ->whereNull('cancelled_at')
            // Só o que de fato vigora: assinado, ou cadastrado direto (vale pela data).
            ->where(fn ($query) => $query->whereNotNull('signed_at')->orWhere('active_without_signature', true))
            ->with('client')
            ->orderBy('ends_at')
            ->get();

        if ($contracts->isEmpty()) {
            $this->info('Nenhum contrato em marco de vencimento hoje.');

            return self::SUCCESS;
        }

        $dryRun = (bool) $this->option('dry-run');
        $rows = [];

        foreach ($contracts as $contract) {
            $dias = max(0, (int) $contract->daysLeft());

            if ($dryRun) {
                $rows[] = [$contract->number, $contract->client->name, $contract->ends_at->format('d/m/Y'), $dias, '—'];

                continue;
            }

            $resultado = (new ContractExpiryAlert($contract))->send();
            $rows[] = [$contract->number, $contract->client->name, $contract->ends_at->format('d/m/Y'), $dias, $resultado['message']];
        }

        $this->newLine();

        if ($dryRun) {
            $this->warn('SIMULAÇÃO — nada foi enviado.');
        }

        $this->table(['Contrato', 'Cliente', 'Fim', 'Dias', 'Resultado'], $rows);

        return self::SUCCESS;
    }
}
