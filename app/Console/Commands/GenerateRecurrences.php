<?php

namespace App\Console\Commands;

use App\Models\Recurrence;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Materializa em lançamentos as recorrências que já venceram — ou que vencem
 * dentro da antecedência pedida.
 *
 * Roda todo dia pelo agendador. É seguro rodar à mão quantas vezes quiser: a
 * geração é idempotente por (recorrência, vencimento).
 */
class GenerateRecurrences extends Command
{
    protected $signature = 'financeiro:gerar-recorrencias
        {--dias=30 : Antecedência em dias — gera o que vence até lá}
        {--dry-run : Mostra o que seria gerado, sem gravar}';

    protected $description = 'Gera os lançamentos das recorrências que venceram ou estão para vencer';

    public function handle(): int
    {
        $days = max(0, (int) $this->option('dias'));
        $until = Carbon::today()->addDays($days);
        $dryRun = (bool) $this->option('dry-run');

        $pending = Recurrence::due($until)->with('client')->orderBy('next_due_at')->get();

        if ($pending->isEmpty()) {
            $this->info('Nada a gerar até '.$until->format('d/m/Y').'.');

            return self::SUCCESS;
        }

        $rows = [];
        $created = 0;

        foreach ($pending as $recurrence) {
            /*
             * Uma recorrência parada há meses tem mais de um vencimento a
             * recuperar. O laço vai gerando até alcançar a janela — mas com
             * teto, para uma data mal digitada não criar mil lançamentos.
             */
            $guard = 0;

            while ($recurrence->isRunning() && $recurrence->next_due_at->lte($until) && $guard++ < 120) {
                $due = $recurrence->next_due_at->copy();

                if ($dryRun) {
                    $rows[] = [$due->format('d/m/Y'), $recurrence->description, $this->money($recurrence->amount)];
                    // Sem gravar, avança só na memória para o laço terminar.
                    $recurrence->next_due_at = Recurrence::advance($due, $recurrence->interval);

                    continue;
                }

                if ($recurrence->generateNext() !== null) {
                    $rows[] = [$due->format('d/m/Y'), $recurrence->description, $this->money($recurrence->amount)];
                    $created++;
                }
            }
        }

        $this->newLine();

        if ($dryRun) {
            $this->warn('SIMULAÇÃO — nada foi gravado.');
        }

        if ($rows === []) {
            $this->info('Tudo já estava gerado.');

            return self::SUCCESS;
        }

        $this->table(['Vencimento', 'Descrição', 'Valor'], $rows);
        $this->info(($dryRun ? count($rows).' lançamentos seriam gerados.' : "{$created} lançamentos gerados."));

        return self::SUCCESS;
    }

    private function money(string $amount): string
    {
        return 'R$ '.number_format((float) $amount, 2, ',', '.');
    }
}
