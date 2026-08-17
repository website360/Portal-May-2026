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

interface Resultado {
    days_late: number;
    late: boolean;
    amount: number;
    discount: number;
    fine: number;
    interest: number;
    daily_rate: number;
    total: number;
}

/** A praxe brasileira: 2% de multa e 1% ao mês de mora. */
const PADRAO = { fine: '2', interest: '1' };

export default function Boleto({ today }: { today: string }) {
    const [valor, setValor] = useState('');
    const [vencimento, setVencimento] = useState('');
    const [pagamento, setPagamento] = useState(today);
    const [multa, setMulta] = useState(PADRAO.fine);
    const [juros, setJuros] = useState(PADRAO.interest);
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
                fine: multa || '0',
                interest: juros || '0',
                discount: currencyToNumber(desconto) || '0',
            });

            const resposta = await fetch(`${route('ferramentas.boleto.calculo')}?${params}`, { headers: { Accept: 'application/json' } });

            if (resposta.ok) setResultado(await resposta.json());
        }, 250);

        return () => clearTimeout(timer);
    }, [valor, vencimento, pagamento, multa, juros, desconto]);

    function limpar() {
        setValor('');
        setVencimento('');
        setPagamento(today);
        setMulta(PADRAO.fine);
        setJuros(PADRAO.interest);
        setDesconto('');
        setResultado(null);
    }

    function copiar() {
        if (!resultado) return;

        navigator.clipboard.writeText(formatMoney(resultado.total).replace(/\s/g, ' '));
        setCopiado(true);
        setTimeout(() => setCopiado(false), 2000);
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Juros e multa de boleto" />

            <div className="animate-fade-in flex min-w-0 flex-1 flex-col gap-6 p-6">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <div className="space-y-1">
                        <h1 className="text-2xl font-bold tracking-tight">Juros e multa de boleto</h1>
                        <p className="text-muted-foreground text-sm">Quanto cobrar de um boleto pago fora do prazo.</p>
                    </div>

                    <Button variant="outline" asChild>
                        <Link href={route('ferramentas.index')}>
                            <ArrowLeft />
                            Ferramentas
                        </Link>
                    </Button>
                </div>

                <div className="grid min-w-0 gap-6 lg:grid-cols-[minmax(0,1fr)_minmax(0,22rem)]">
                    <Card>
                        <CardContent className="grid gap-5 p-5">
                            <div className="grid gap-5 sm:grid-cols-2">
                                <Campo label="Valor do boleto" required>
                                    <CurrencyInput autoFocus value={valor} onChange={setValor} placeholder="0,00" />
                                </Campo>

                                <Campo label="Desconto" hint="Abatimento combinado, se houver.">
                                    <CurrencyInput value={desconto} onChange={setDesconto} placeholder="0,00" />
                                </Campo>
                            </div>

                            <div className="grid gap-5 sm:grid-cols-2">
                                <Campo label="Vencimento" required>
                                    <Input type="date" value={vencimento} onChange={(e) => setVencimento(e.target.value)} />
                                </Campo>

                                <Campo label="Data do pagamento" required>
                                    <Input type="date" value={pagamento} onChange={(e) => setPagamento(e.target.value)} />
                                </Campo>
                            </div>

                            <div className="grid gap-5 sm:grid-cols-2">
                                <Campo label="Multa (%)" hint="Aplicada uma vez. A praxe é 2%.">
                                    <Input inputMode="decimal" value={multa} onChange={(e) => setMulta(e.target.value.replace(',', '.'))} />
                                </Campo>

                                <Campo label="Juros ao mês (%)" hint="Cobrados por dia de atraso. A praxe é 1%.">
                                    <Input inputMode="decimal" value={juros} onChange={(e) => setJuros(e.target.value.replace(',', '.'))} />
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

                    <Card className="lg:sticky lg:top-6 lg:self-start">
                        <CardContent className="p-5">
                            {resultado === null ? (
                                <p className="text-muted-foreground py-8 text-center text-sm">Preencha o valor e as datas.</p>
                            ) : (
                                <div className="space-y-4">
                                    <div
                                        className={cn(
                                            'rounded-lg px-3 py-2 text-center text-sm font-medium',
                                            resultado.late ? 'bg-destructive/10 text-destructive' : 'bg-success/10 text-success',
                                        )}
                                    >
                                        {resultado.late
                                            ? `${resultado.days_late} ${resultado.days_late === 1 ? 'dia' : 'dias'} de atraso`
                                            : 'Dentro do prazo'}
                                    </div>

                                    <dl className="space-y-2 text-sm">
                                        <Linha rotulo="Valor original" valor={resultado.amount} />

                                        {resultado.fine > 0 && <Linha rotulo="Multa" valor={resultado.fine} tom="acrescimo" />}

                                        {resultado.interest > 0 && (
                                            <Linha
                                                rotulo={`Juros (${resultado.daily_rate.toFixed(4).replace('.', ',')}% ao dia)`}
                                                valor={resultado.interest}
                                                tom="acrescimo"
                                            />
                                        )}

                                        {resultado.discount > 0 && <Linha rotulo="Desconto" valor={-resultado.discount} tom="desconto" />}
                                    </dl>

                                    <div className="border-t pt-3">
                                        <p className="text-muted-foreground text-xs">Total a cobrar</p>
                                        <p className="tabular text-3xl font-bold tracking-tight">{formatMoney(resultado.total)}</p>
                                    </div>

                                    <Button variant="outline" className="w-full" onClick={copiar}>
                                        {copiado ? <Check /> : <Copy />}
                                        {copiado ? 'Copiado' : 'Copiar o total'}
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
        <div className="grid gap-1.5">
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
