<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Support\Asaas;
use App\Support\AsaasLinks;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Conciliação das cobranças do sistema com as do Asaas.
 *
 * Os IDs são diferentes nos dois lados, então casamos por documento do cliente
 * (CPF/CNPJ), valor e vencimento. Só leitura aqui: aponta pagas, casadas e as
 * "soltas" de cada lado; criar a faltante e dar baixa são ações à parte.
 */
class ReconciliationController extends Controller
{
    /** Status do Asaas que contam como pago. */
    private const PAID = ['RECEIVED', 'CONFIRMED', 'RECEIVED_IN_CASH', 'DUNNING_RECEIVED'];

    public function index(Request $request): Response
    {
        if (! Asaas::configured()) {
            return Inertia::render('financeiro/conciliacao', [
                'configured' => false,
                'month' => Carbon::today()->format('Y-m'),
                'matched' => [],
                'systemOnly' => [],
                'asaasOnly' => [],
                'error' => null,
            ]);
        }

        $month = $request->has('month') ? $request->string('month')->toString() : Carbon::today()->format('Y-m');
        $start = Carbon::parse($month.'-01')->startOfMonth();
        $end = (clone $start)->endOfMonth();

        $digits = fn ($v) => preg_replace('/\D/', '', (string) $v) ?? '';
        $keyOf = fn (string $doc, float $value, string $date) => $doc.'|'.number_format($value, 2, '.', '').'|'.$date;

        // 1) Recebíveis do sistema no mês.
        $sistema = Transaction::query()
            ->where('type', Transaction::TYPE_RECEIVABLE)
            ->whereBetween('due_date', [$start->toDateString(), $end->toDateString()])
            ->with('client:id,name,trade_name,document')
            ->get();

        // 2) Pagamentos do Asaas no mês (filtro por vencimento + conferência no PHP).
        $payments = Asaas::paginate('/payments', [
            'dueDate[ge]' => $start->toDateString(),
            'dueDate[le]' => $end->toDateString(),
        ]);

        if ($payments === [] && ! Asaas::test()['ok']) {
            return Inertia::render('financeiro/conciliacao', [
                'configured' => true,
                'month' => $month,
                'matched' => [],
                'systemOnly' => [],
                'asaasOnly' => [],
                'error' => 'Não foi possível ler os pagamentos do Asaas. Verifique a conexão em Configurações → Asaas.',
            ]);
        }

        // 3) Documento de cada cliente do Asaas (id -> cpfCnpj).
        $docByCustomer = [];
        $nameByCustomer = [];
        foreach (Asaas::paginate('/customers') as $c) {
            $cid = (string) ($c['id'] ?? '');
            $docByCustomer[$cid] = $digits($c['cpfCnpj'] ?? '');
            $nameByCustomer[$cid] = (string) ($c['name'] ?? '');
        }

        // Índice dos pagamentos por chave (doc|valor|vencimento).
        $asaasItems = [];
        $asaasByKey = [];
        foreach ($payments as $p) {
            $due = $p['dueDate'] ?? null;

            // Conferência no PHP: só o mês pedido (caso o filtro do Asaas não tenha valido).
            if (! is_string($due) || $due < $start->toDateString() || $due > $end->toDateString()) {
                continue;
            }

            $doc = $docByCustomer[(string) ($p['customer'] ?? '')] ?? '';
            $item = [
                'id' => (string) ($p['id'] ?? ''),
                'value' => (float) ($p['value'] ?? 0),
                'due_date' => $due,
                'status' => $p['status'] ?? null,
                'paid' => in_array($p['status'] ?? '', self::PAID, true),
                'invoice_number' => $p['invoiceNumber'] ?? null,
                'invoice_url' => $p['invoiceUrl'] ?? ($p['bankSlipUrl'] ?? null),
                'doc' => $doc,
                'client_name' => $nameByCustomer[(string) ($p['customer'] ?? '')] ?? '',
                'description' => (string) ($p['description'] ?? ''),
            ];
            $asaasItems[] = $item;

            if ($doc !== '') {
                $asaasByKey[$keyOf($doc, $item['value'], $due)][] = $item;
            }
        }

        $matched = [];
        $systemOnly = [];
        $usedAsaas = [];

        foreach ($sistema as $t) {
            $doc = $digits($t->client?->document);
            $key = $doc !== '' ? $keyOf($doc, (float) $t->amount, $t->due_date->toDateString()) : null;

            $match = null;
            if ($key !== null && ! empty($asaasByKey[$key])) {
                foreach ($asaasByKey[$key] as $cand) {
                    if (! in_array($cand['id'], $usedAsaas, true)) {
                        $match = $cand;
                        break;
                    }
                }
            }

            $row = [
                'id' => $t->id,
                'client' => $t->client?->display_name,
                'description' => $t->description,
                'amount' => (float) $t->amount,
                'due_date_label' => $t->due_date->format('d/m/Y'),
                'system_paid' => $t->paid_at !== null,
            ];

            if ($match !== null) {
                $usedAsaas[] = $match['id'];

                // Conciliação é manual: aqui é só sugestão. "linked" diz se já foi confirmada.
                $existing = AsaasLinks::forTransaction($t->id);
                $linked = $existing !== null && ($existing['payment_id'] ?? null) === $match['id'];

                $matched[] = $row + [
                    'asaas_id' => $match['id'],
                    'asaas_status' => $match['status'],
                    'asaas_paid' => $match['paid'],
                    'invoice_number' => $match['invoice_number'],
                    'invoice_url' => $match['invoice_url'],
                    'linked' => $linked,
                ];
            } else {
                $systemOnly[] = $row;
            }
        }

        $asaasOnly = [];
        foreach ($asaasItems as $item) {
            if (! in_array($item['id'], $usedAsaas, true)) {
                $asaasOnly[] = [
                    'asaas_id' => $item['id'],
                    'doc' => $item['doc'],
                    'client_name' => $item['client_name'],
                    'description' => $item['description'],
                    'amount' => $item['value'],
                    'due_date_label' => Carbon::parse($item['due_date'])->format('d/m/Y'),
                    'due_date' => $item['due_date'],
                    'status' => $item['status'],
                    'paid' => $item['paid'],
                    'invoice_number' => $item['invoice_number'],
                    'invoice_url' => $item['invoice_url'],
                ];
            }
        }

        return Inertia::render('financeiro/conciliacao', [
            'configured' => true,
            'month' => $month,
            'matched' => $matched,
            'systemOnly' => $systemOnly,
            'asaasOnly' => $asaasOnly,
            'error' => null,
        ]);
    }

    /** Dá baixa num lançamento (o Asaas já marcou pago). */
    public function darBaixa(Transaction $lancamento): RedirectResponse
    {
        if ($lancamento->paid_at === null) {
            $lancamento->settle();
            $lancamento->save();
        }

        return back()->with('success', 'Baixa registrada.');
    }

    /** Confirma manualmente o vínculo das cobranças casadas (conciliar). */
    public function conciliar(Request $request): RedirectResponse
    {
        $itens = (array) $request->input('items', []);

        if ($itens === []) {
            return back()->with('warning', 'Selecione ao menos uma.');
        }

        $ok = 0;

        foreach ($itens as $item) {
            $txId = (int) ($item['transaction_id'] ?? 0);

            if ($txId <= 0) {
                continue;
            }

            AsaasLinks::set($txId, (string) ($item['asaas_id'] ?? ''), $item['invoice_number'] ?? null, $item['invoice_url'] ?? null);
            $ok++;
        }

        return back()->with('success', "{$ok} conciliada(s).");
    }

    /** Cria no Asaas as cobranças selecionadas (só no sistema). */
    public function criarNoAsaas(Request $request): RedirectResponse
    {
        $ids = array_map('intval', (array) $request->input('ids', []));

        if ($ids === []) {
            return back()->with('warning', 'Selecione ao menos uma cobrança.');
        }

        $transacoes = Transaction::query()->whereIn('id', $ids)->with('client')->get();
        $ok = 0;
        $falhas = [];

        foreach ($transacoes as $t) {
            $doc = preg_replace('/\D/', '', (string) $t->client?->document) ?? '';

            if ($doc === '') {
                $falhas[] = ($t->client?->display_name ?? $t->description).' (sem CPF/CNPJ)';

                continue;
            }

            $customerId = Asaas::customerFor($doc, (string) $t->client->name, $t->client->email, $t->client->phone);

            if ($customerId === null) {
                $falhas[] = (string) $t->client?->display_name.' (cliente no Asaas)';

                continue;
            }

            $r = Asaas::post('/payments', [
                'customer' => $customerId,
                'billingType' => 'BOLETO',
                'value' => (float) $t->amount,
                'dueDate' => $t->due_date->toDateString(),
                'description' => (string) $t->description,
                'externalReference' => 'tx-'.$t->id,
            ]);

            if (! $r['ok']) {
                $falhas[] = (string) $t->client?->display_name.': '.$r['message'];

                continue;
            }

            $p = $r['data'] ?? [];
            AsaasLinks::set($t->id, $p['id'] ?? null, $p['invoiceNumber'] ?? null, $p['invoiceUrl'] ?? ($p['bankSlipUrl'] ?? null));
            $ok++;
        }

        $msg = "{$ok} cobran\u{e7}a(s) criada(s) no Asaas.";

        if ($falhas !== []) {
            $msg .= ' Falharam: '.implode('; ', array_slice($falhas, 0, 5)).(count($falhas) > 5 ? '…' : '');
        }

        return back()->with($falhas !== [] ? 'warning' : 'success', $msg);
    }

    /** Cria no sistema os lançamentos selecionados (só no Asaas). */
    public function criarLancamento(Request $request): RedirectResponse
    {
        $itens = (array) $request->input('items', []);

        if ($itens === []) {
            return back()->with('warning', 'Selecione ao menos uma cobrança.');
        }

        $clientes = \App\Models\Client::query()->get(['id', 'document']);
        $ok = 0;

        foreach ($itens as $item) {
            $amount = (float) ($item['amount'] ?? 0);
            $due = (string) ($item['due_date'] ?? '');

            if ($amount <= 0 || $due === '') {
                continue;
            }

            $doc = preg_replace('/\D/', '', (string) ($item['doc'] ?? '')) ?? '';
            $client = $doc !== ''
                ? $clientes->first(fn ($c) => (preg_replace('/\D/', '', (string) $c->document) ?? '') === $doc)
                : null;

            $tx = Transaction::create([
                'type' => Transaction::TYPE_RECEIVABLE,
                'description' => (string) ($item['description'] ?? '') ?: ((string) ($item['client_name'] ?? '') ?: 'Cobrança Asaas'),
                'amount' => $amount,
                'due_date' => $due,
                'client_id' => $client?->id,
                'counterpart' => $client !== null ? null : ((string) ($item['client_name'] ?? '') ?: null),
            ]);

            if (! empty($item['asaas_id'])) {
                AsaasLinks::set($tx->id, (string) $item['asaas_id'], $item['invoice_number'] ?? null, $item['invoice_url'] ?? null);
            }

            $ok++;
        }

        return back()->with('success', "{$ok} lan\u{e7}amento(s) criado(s) no sistema.");
    }
}
