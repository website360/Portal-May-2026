<?php

namespace App\Http\Controllers\Maintenance;

use App\Http\Controllers\Controller;
use App\Models\Maintenance;
use App\Models\MaintenancePlan;
use App\Support\MaintenanceChecklist;
use App\Support\MaintenanceReport;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * O registro de uma manutenção executada.
 */
class MaintenanceController extends Controller
{
    public function store(Request $request, MaintenancePlan $plano): RedirectResponse
    {
        $data = $this->validated($request);

        $maintenance = $plano->maintenances()->create([
            'user_id' => $request->user()->id,
            'performed_at' => $data['performed_at'],
            'items' => MaintenanceChecklist::from($data['items'] ?? []),
            'notes' => $data['notes'] ?? null,
        ]);

        $message = "Manutenção de {$plano->site_url} registrada.";

        if ($data['notify'] ?? false) {
            $report = (new MaintenanceReport($maintenance))->send();

            /*
             * O envio falhar não desfaz o registro — a manutenção aconteceu. A
             * tela avisa para quem registrou poder reenviar pelo histórico.
             */
            if (! $report['ok']) {
                return back()->with('warning', "{$message} Mas o relatório não saiu: {$report['message']}");
            }

            $message .= ' Relatório enviado no WhatsApp.';
        }

        return back()->with('success', $message);
    }

    public function update(Request $request, Maintenance $manutencao): RedirectResponse
    {
        $data = $this->validated($request);

        $manutencao->update([
            'performed_at' => $data['performed_at'],
            'items' => MaintenanceChecklist::from($data['items'] ?? []),
            'notes' => $data['notes'] ?? null,
        ]);

        return back()->with('success', 'Manutenção atualizada.');
    }

    /**
     * Reenvia o relatório de uma manutenção já registrada.
     *
     * Existe porque a causa mais comum de falha — WhatsApp desconectado, cliente
     * sem telefone — se resolve depois, e refazer a manutenção só para reenviar
     * o texto seria mentira no histórico.
     */
    public function resend(Maintenance $manutencao): RedirectResponse
    {
        $report = (new MaintenanceReport($manutencao))->send();

        return back()->with($report['ok'] ? 'success' : 'warning', $report['message']);
    }

    public function destroy(Maintenance $manutencao): RedirectResponse
    {
        $manutencao->delete();

        return back()->with('success', 'Manutenção excluída do histórico.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'performed_at' => ['required', 'date', 'before_or_equal:today'],
            'items' => ['array'],
            'items.*' => [Rule::in(array_keys(MaintenanceChecklist::RESULTS))],
            'notes' => ['nullable', 'string', 'max:2000'],
            'notify' => ['boolean'],
        ], [
            'performed_at.before_or_equal' => 'A manutenção não pode ter sido feita no futuro.',
        ], [
            'performed_at' => 'data',
        ]);
    }
}
