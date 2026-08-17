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
            'description' => 'Quanto cobrar de boletos pagos com atraso: multa, mora por dia e total.',
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
            'defaults' => [
                'fine' => LateFee::DEFAULT_FINE,
                'interest' => LateFee::DEFAULT_INTEREST,
                'max_installments' => LateFee::MAX_INSTALLMENTS,
            ],
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
            'count' => ['nullable', 'integer', 'min:1', 'max:'.LateFee::MAX_INSTALLMENTS],
            'fine' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'interest' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'interest_unit' => ['nullable', 'in:month,day'],
            'discount' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
        ], [], [
            'amount' => 'valor',
            'due_at' => 'vencimento',
            'paid_at' => 'data do pagamento',
            'count' => 'quantidade de boletos',
            'fine' => 'multa',
            'interest' => 'juros',
            'discount' => 'desconto',
        ]);

        $juros = (float) ($data['interest'] ?? LateFee::DEFAULT_INTEREST);

        // A lei fala em 1% ao mês, e o boleto costuma imprimir "0,0333% ao dia".
        // São a mesma coisa; quem digita escolhe a forma que tem na mão.
        if (($data['interest_unit'] ?? 'month') === 'day') {
            $juros *= 30;
        }

        return response()->json(LateFee::schedule(
            (float) $data['amount'],
            Carbon::parse($data['due_at']),
            (int) ($data['count'] ?? 1),
            Carbon::parse($data['paid_at']),
            (float) ($data['fine'] ?? LateFee::DEFAULT_FINE),
            $juros,
            (float) ($data['discount'] ?? 0),
        ));
    }
}
