import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { CurrencyInput } from '@/components/ui/currency-input';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import { formatMoney } from '@/lib/format';
import { currencyToNumber } from '@/lib/masks';
import { cn } from '@/lib/utils';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, Check, Copy, RotateCcw } from 'lucide-react';
import { useEffect, useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Ferramentas', href: '/ferramentas' },
    { title: 'Juros e multa', href: '/ferramentas/boleto' },
];

interface Parcela {
    number: number;
    due_at: string;
    days_late: number;
    late: boolean;
    amount: number;
    fine: number;
    interest: number;
    total: number;
}

interface Resultado {
    installments: Parcela[];
    totals: {
        count: number;
        late_count: number;
        amount: number;
        fine: number;
        interest: number;
        discount: number;
        total: number;
        daily_rate: number;
    };
}

interface Padroes {
    fine: number;
    interest: number;
    max_installments: number;
}

type Unidade = 'month' | 'day';

/** Vira "0,0333" — a forma que o boleto imprime. */
function aoDia(mensal: number): string {
    return (mensal / 30).toFixed(4).replace(/0+$/, '').replace('.', ',');
}

function dataCurta(iso: string): string {
    const [ano, mes, dia] = iso.split('-');

    return `${dia}/${mes}/${ano}`;
}

export default function Boleto({ today, defaults }: { today: string; defaults: Padroes }) {
    const [valor, setValor] = useState('');
    const [quantidade, setQuantidade] = useState('1');
    const [vencimento, setVencimento] = useState('');
    const [pagamento, setPagamento] = useState(today);
    const [multa, setMulta] = useState(String(defaults.fine));
    const [juros, setJuros] = useState(aoDia(defaults.interest));
    const [unidade, setUnidade] = useState<Unidade>('day');
    const [desconto, setDesconto] = useState('');

    const [resultado, setResultado] = useState<Resultado | null>(null);
    const [copiado, setCopiado] = useState(false);

    /*
     * A conta fica no servidor: uma segunda implementação aqui divergiria da
     * primeira no primeiro arredondamento, e isto vira cobrança para o cliente.
     * O atraso curto faz o resultado parecer instantâneo mesmo assim.
     */
    useEffect(() => {
        const numero = currencyToNumber(valor);

        if (!numero || !vencimento || !pagamento) {
            setResultado(null);
            return;
        }

        const timer = setTimeout(async () => {
            const params = new URLSearchParams({
                amount: numero,
                due_at: vencimento,
                paid_at: pagamento,
                count: quantidade || '1',
                // Na tela a taxa aparece como 0,0333; o servidor só entende ponto.
                fine: (multa || '0').replace(',', '.'),
                interest: (juros || '0').replace(',', '.'),
                interest_unit: unidade,
                discount: currencyToNumber(desconto) || '0',
            });

            const resposta = await fetch(`${route('ferramentas.boleto.calculo')}?${params}`, { headers: { Accept: 'application/json' } });

            if (resposta.ok) setResultado(await resposta.json());
        }, 250);

        return () => clearTimeout(timer);
    }, [valor, quantidade, vencimento, pagamento, multa, juros, unidade, desconto]);

    /** Trocar a unidade não pode mudar o juro: 1% ao mês e 0,0333% ao dia são o mesmo. */
    function trocarUnidade(nova: Unidade) {
        if (nova === unidade) return;

        const atual = Number(juros.replace(',', '.')) || 0;
        const convertido = nova === 'day' ? atual / 30 : atual * 30;

        setUnidade(nova);
        setJuros(convertido.toFixed(nova === 'day' ? 4 : 2).replace(/\.?0+$/, ''));
    }

    function limpar() {
        setValor('');
        setQuantidade('1');
        setVencimento('');
        setPagamento(today);
        setMulta(String(defaults.fine));
        setUnidade('day');
        setJuros(aoDia(defaults.interest));
        setDesconto('');
        setResultado(null);
    }

    /*
     * O resumo vai para o WhatsApp do cliente, então sai como texto de gente:
     * uma linha por boleto, com data e atraso, e o total no fim.
     */
    function textoParaCopiar(r: Resultado): string {
        if (r.totals.count === 1) return formatMoney(r.totals.total);

        const linhas = r.installments.map(
            (p) => `${dataCurta(p.due_at)} — ${p.days_late} ${p.days_late === 1 ? 'dia' : 'dias'} — ${formatMoney(p.total)}`,
        );

        return [`${r.totals.count} boletos de ${formatMoney(r.installments[0].amount)}`, ...linhas, `Total: ${formatMoney(r.totals.total)}`].join(
            '\n',
        );
    }

    function copiar() {
        if (!resultado) return;

        navigator.clipboard.writeText(textoParaCopiar(resultado).replace(/ /g, ' '));
        setCopiado(true);
        setTimeout(() => setCopiado(false), 2000);
    }

    const varios = (resultado?.totals.count ?? 1) > 1;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Juros e multa de boleto" />

            <div className="animate-fade-in flex min-w-0 flex-1 flex-col gap-6 p-6">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <div className="space-y-1">
                        <h1 className="text-2xl font-bold tracking-tight">Juros e multa de boleto</h1>
                        <p className="text-muted-foreground text-sm">Quanto cobrar de boletos pagos fora do prazo.</p>
                    </div>

                    <Button variant="outline" asChild>
                        <Link href={route('ferramentas.index')}>
                            <ArrowLeft />
                            Ferramentas
                        </Link>
                    </Button>
                </div>

                <div className="grid min-w-0 gap-6 lg:grid-cols-[minmax(0,1fr)_minmax(0,22rem)]">
                    <div className="grid min-w-0 gap-6">
                        <Card>
                            <CardContent className="grid gap-5 p-5">
                                <div className="grid gap-5 sm:grid-cols-[minmax(0,1fr)_8rem]">
                                    <Campo label="Valor de cada boleto" required>
                                        <CurrencyInput autoFocus value={valor} onChange={setValor} placeholder="0,00" />
                                    </Campo>

                                    <Campo label="Quantos são" hint="Um por mês.">
                                        <Input
                                            inputMode="numeric"
                                            value={quantidade}
                                            onChange={(e) => setQuantidade(e.target.value.replace(/\D/g, '').slice(0, 2))}
                                            onBlur={() => setQuantidade((q) => String(Math.min(Math.max(Number(q) || 1, 1), defaults.max_installments)))}
                                        />
                                    </Campo>
                                </div>

                                <div className="grid gap-5 sm:grid-cols-2">
                                    <Campo label={varios ? 'Primeiro vencimento' : 'Vencimento'} required hint={varios ? 'Os demais vencem de mês em mês.' : undefined}>
                                        <Input type="date" value={vencimento} onChange={(e) => setVencimento(e.target.value)} />
                                    </Campo>

                                    <Campo label="Data do pagamento" required>
                                        <Input type="date" value={pagamento} onChange={(e) => setPagamento(e.target.value)} />
                                    </Campo>
                                </div>

                                <div className="grid gap-5 sm:grid-cols-[minmax(0,1fr)_minmax(0,1fr)]">
                                    <Campo label="Multa (%)" hint="Aplicada uma vez sobre cada boleto.">
                                        <Input inputMode="decimal" value={multa} onChange={(e) => setMulta(e.target.value.replace(',', '.'))} />
                                    </Campo>

                                    <Campo
                                        label="Juros"
                                        hint={unidade === 'day' ? '0,0333% ao dia é o teto legal (1% ao mês).' : '1% ao mês é o teto legal.'}
                                    >
                                        <div className="flex gap-2">
                                            <Input
                                                inputMode="decimal"
                                                className="min-w-0 flex-1"
                                                value={juros}
                                                onChange={(e) => setJuros(e.target.value.replace(',', '.'))}
                                            />
                                            <div className="bg-muted flex shrink-0 rounded-md p-0.5">
                                                {(
                                                    [
                                                        ['day', 'ao dia'],
                                                        ['month', 'ao mês'],
                                                    ] as const
                                                ).map(([chave, rotulo]) => (
                                                    <button
                                                        key={chave}
                                                        type="button"
                                                        onClick={() => trocarUnidade(chave)}
                                                        className={cn(
                                                            'rounded px-2.5 text-xs font-medium transition-colors',
                                                            unidade === chave
                                                                ? 'bg-background text-foreground shadow-sm'
                                                                : 'text-muted-foreground hover:text-foreground',
                                                        )}
                                                    >
                                                        {rotulo}
                                                    </button>
                                                ))}
                                            </div>
                                        </div>
                                    </Campo>
                                </div>

                                <div className="grid gap-5 sm:grid-cols-2">
                                    <Campo label="Desconto" hint="Abatimento no total, se houver.">
                                        <CurrencyInput value={desconto} onChange={setDesconto} placeholder="0,00" />
                                    </Campo>
                                </div>

                                <div>
                                    <Button type="button" variant="ghost" size="sm" onClick={limpar}>
                                        <RotateCcw />
                                        Limpar
                                    </Button>
                                </div>
                            </CardContent>
                        </Card>

                        {resultado && varios && (
                            <Card>
                                <CardContent className="min-w-0 overflow-x-auto p-0">
                                    <table className="w-full text-sm">
                                        <thead className="text-muted-foreground border-b text-xs">
                                            <tr>
                                                <th className="px-4 py-2.5 text-left font-medium">Vencimento</th>
                                                <th className="px-4 py-2.5 text-right font-medium">Atraso</th>
                                                <th className="px-4 py-2.5 text-right font-medium">Multa</th>
                                                <th className="px-4 py-2.5 text-right font-medium">Juros</th>
                                                <th className="px-4 py-2.5 text-right font-medium">Total</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {resultado.installments.map((p) => (
                                                <tr key={p.number} className="border-b last:border-0">
                                                    <td className="px-4 py-2.5 font-medium">{dataCurta(p.due_at)}</td>
                                                    <td className={cn('tabular px-4 py-2.5 text-right', !p.late && 'text-muted-foreground')}>
                                                        {p.late ? `${p.days_late} d` : 'em dia'}
                                                    </td>
                                                    <td className="tabular text-muted-foreground px-4 py-2.5 text-right">{formatMoney(p.fine)}</td>
                                                    <td className="tabular text-muted-foreground px-4 py-2.5 text-right">
                                                        {formatMoney(p.interest)}
                                                    </td>
                                                    <td className="tabular px-4 py-2.5 text-right font-medium">{formatMoney(p.total)}</td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </CardContent>
                            </Card>
                        )}
                    </div>

                    <Card className="lg:sticky lg:top-6 lg:self-start">
                        <CardContent className="p-5">
                            {resultado === null ? (
                                <p className="text-muted-foreground py-8 text-center text-sm">Preencha o valor e as datas.</p>
                            ) : (
                                <div className="space-y-4">
                                    <div
                                        className={cn(
                                            'rounded-lg px-3 py-2 text-center text-sm font-medium',
                                            resultado.totals.late_count ? 'bg-destructive/10 text-destructive' : 'bg-success/10 text-success',
                                        )}
                                    >
                                        {resultado.totals.late_count === 0
                                            ? 'Dentro do prazo'
                                            : varios
                                              ? `${resultado.totals.late_count} de ${resultado.totals.count} em atraso`
                                              : `${resultado.installments[0].days_late} ${resultado.installments[0].days_late === 1 ? 'dia' : 'dias'} de atraso`}
                                    </div>

                                    <dl className="space-y-2 text-sm">
                                        <Linha
                                            rotulo={varios ? `Valor original (${resultado.totals.count}×)` : 'Valor original'}
                                            valor={resultado.totals.amount}
                                        />

                                        {resultado.totals.fine > 0 && <Linha rotulo="Multa" valor={resultado.totals.fine} tom="acrescimo" />}

                                        {resultado.totals.interest > 0 && (
                                            <Linha
                                                rotulo={`Juros (${resultado.totals.daily_rate.toFixed(4).replace('.', ',')}% ao dia)`}
                                                valor={resultado.totals.interest}
                                                tom="acrescimo"
                                            />
                                        )}

                                        {resultado.totals.discount > 0 && (
                                            <Linha rotulo="Desconto" valor={-resultado.totals.discount} tom="desconto" />
                                        )}
                                    </dl>

                                    <div className="border-t pt-3">
                                        <p className="text-muted-foreground text-xs">Total a cobrar</p>
                                        <p className="tabular text-3xl font-bold tracking-tight">{formatMoney(resultado.totals.total)}</p>
                                    </div>

                                    <Button variant="outline" className="w-full" onClick={copiar}>
                                        {copiado ? <Check /> : <Copy />}
                                        {copiado ? 'Copiado' : varios ? 'Copiar o resumo' : 'Copiar o total'}
                                    </Button>
                                </div>
                            )}
                        </CardContent>
                    </Card>
                </div>
            </div>
        </AppLayout>
    );
}

function Campo({ label, required, hint, children }: { label: string; required?: boolean; hint?: string; children: React.ReactNode }) {
    return (
        <div className="grid content-start gap-1.5">
            <Label>
                {label}
                {required && <span className="text-destructive ml-0.5">*</span>}
            </Label>
            {children}
            {hint && <p className="text-muted-foreground text-xs">{hint}</p>}
        </div>
    );
}

function Linha({ rotulo, valor, tom }: { rotulo: string; valor: number; tom?: 'acrescimo' | 'desconto' }) {
    return (
        <div className="flex items-baseline justify-between gap-3">
            <dt className="text-muted-foreground">{rotulo}</dt>
            <dd className={cn('tabular font-medium', tom === 'acrescimo' && 'text-destructive', tom === 'desconto' && 'text-success')}>
                {tom === 'acrescimo' && '+ '}
                {formatMoney(Math.abs(valor))}
            </dd>
        </div>
    );
}
