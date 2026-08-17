<?php

namespace App\Http\Controllers\Tools;

use App\Http\Controllers\Controller;
use App\Support\LateFee;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Ferramentas: micro-sistemas que resolvem uma conta do dia a dia.
 *
 * Cada uma é uma tela só, sem cadastro e sem histórico — entra, resolve, sai.
 * A lista fica aqui em vez de num banco porque ferramenta é código, não dado:
 * acrescentar uma é escrever uma tela e uma linha nesta lista.
 */
class ToolController extends Controller
{
    /**
     * @var list<array<string, string>>
     */
    private const TOOLS = [
        [
            'slug' => 'boleto',
            'name' => 'Juros e multa de boleto',
            'description' => 'Quanto cobrar de um boleto pago com atraso: multa, mora por dia e total.',
            'icon' => 'receipt',
        ],
    ];

    public function index(): Response
    {
        return Inertia::render('ferramentas/index', ['tools' => self::TOOLS]);
    }

    public function boleto(): Response
    {
        return Inertia::render('ferramentas/boleto', [
            'today' => Carbon::today()->format('Y-m-d'),
        ]);
    }

    /**
     * A conta do atraso.
     *
     * Vai por JSON e fica no servidor: uma segunda implementação em TypeScript
     * para ganhar alguns milissegundos divergiria da primeira no primeiro
     * arredondamento — e isto aqui vira cobrança para o cliente.
     */
    public function calcularBoleto(Request $request): JsonResponse
    {
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:0', 'max:99999999'],
            'due_at' => ['required', 'date'],
            'paid_at' => ['required', 'date'],
            'fine' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'interest' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'discount' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
        ], [], [
            'amount' => 'valor',
            'due_at' => 'vencimento',
            'paid_at' => 'data do pagamento',
            'fine' => 'multa',
            'interest' => 'juros',
            'discount' => 'desconto',
        ]);

        return response()->json(LateFee::calculate(
            (float) $data['amount'],
            Carbon::parse($data['due_at']),
            Carbon::parse($data['paid_at']),
            (float) ($data['fine'] ?? 2),
            (float) ($data['interest'] ?? 1),
            (float) ($data['discount'] ?? 0),
        ));
    }
}
