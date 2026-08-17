import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { ConfirmDialog } from '@/components/ui/confirm-dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import SettingsLayout from '@/layouts/settings/layout';
import { type BreadcrumbItem } from '@/types';
import { Head, router, useForm } from '@inertiajs/react';
import { CircleCheck, CircleSlash, Loader2, QrCode, RefreshCw, Smartphone } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Configurações', href: '/configuracoes/perfil' },
    { title: 'WhatsApp', href: '/configuracoes/whatsapp' },
];

interface Connection {
    base_url: string;
    instance: string;
    has_key: boolean;
    status: 'connected' | 'connecting' | 'disconnected';
    number: string | null;
    checked_at: string | null;
}

export default function Whatsapp({ connection }: { connection: Connection | null }) {
    const [qr, setQr] = useState<string | null>(null);
    const [aviso, setAviso] = useState<string | null>(null);
    const [carregando, setCarregando] = useState(false);
    const [status, setStatus] = useState(connection?.status ?? 'disconnected');
    const [desconectando, setDesconectando] = useState(false);
    const timer = useRef<ReturnType<typeof setInterval> | null>(null);

    const { data, setData, put, processing, errors } = useForm({
        base_url: connection?.base_url ?? '',
        instance: connection?.instance ?? 'agencia-may',
        api_key: '',
    });

    /*
     * Enquanto o QR está na tela, pergunta o estado a cada 4 segundos: o
     * pareamento acontece no celular, e a tela não tem como saber de outro
     * jeito. Para assim que conectar — ou quando o QR sai da tela.
     */
    useEffect(() => {
        if (qr === null) {
            return;
        }

        timer.current = setInterval(async () => {
            const resposta = await fetch(route('configuracoes.whatsapp.estado'), { headers: { Accept: 'application/json' } });
            const estado = await resposta.json();

            setStatus(estado.status);

            if (estado.status === 'connected') {
                setQr(null);
                setAviso('Aparelho conectado.');
                router.reload({ only: ['connection'] });
            }
        }, 4000);

        return () => {
            if (timer.current) clearInterval(timer.current);
        };
    }, [qr]);

    async function gerarQr() {
        setCarregando(true);
        setAviso(null);

        const resposta = await fetch(route('configuracoes.whatsapp.qrcode'), { headers: { Accept: 'application/json' } });
        const dados = await resposta.json();

        setQr(dados.qr ?? null);
        setAviso(dados.message);
        setCarregando(false);
    }

    async function verificar() {
        setCarregando(true);

        const resposta = await fetch(route('configuracoes.whatsapp.estado'), { headers: { Accept: 'application/json' } });
        const estado = await resposta.json();

        setStatus(estado.status);
        setAviso(estado.message);
        setCarregando(false);
        router.reload({ only: ['connection'] });
    }

    const conectado = status === 'connected';

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="WhatsApp" />

            <SettingsLayout>
                <div className="flex min-w-0 flex-col gap-6">
                    <div className="space-y-1">
                        <h2 className="text-lg font-semibold">WhatsApp</h2>
                        <p className="text-muted-foreground text-sm">
                            Conexão com o servidor Evolution. Pareado o aparelho, os módulos passam a poder enviar mensagem.
                        </p>
                    </div>

                    <Card>
                        <CardHeader className="flex flex-wrap items-start justify-between gap-3">
                            <div className="space-y-1.5">
                                <CardTitle className="flex items-center gap-2">
                                    <Smartphone className="size-4" />
                                    Situação
                                </CardTitle>
                                <CardDescription>
                                    {connection?.checked_at ? `Verificado em ${connection.checked_at}` : 'Ainda não verificado'}
                                </CardDescription>
                            </div>

                            <div className="flex items-center gap-2">
                                <Badge variant={conectado ? 'success' : status === 'connecting' ? 'warning' : 'muted'} className="gap-1">
                                    {conectado ? <CircleCheck className="size-3" /> : <CircleSlash className="size-3" />}
                                    {conectado ? 'Conectado' : status === 'connecting' ? 'Conectando' : 'Desconectado'}
                                </Badge>

                                {connection && (
                                    <Button variant="outline" size="sm" onClick={verificar} disabled={carregando}>
                                        {carregando ? <Loader2 className="animate-spin" /> : <RefreshCw />}
                                        Verificar
                                    </Button>
                                )}
                            </div>
                        </CardHeader>

                        {connection?.number && (
                            <CardContent className="pt-0">
                                <p className="text-muted-foreground text-sm">
                                    Número pareado: <span className="text-foreground font-medium">{connection.number}</span>
                                </p>
                            </CardContent>
                        )}
                    </Card>

                    <Card>
                        <form
                            onSubmit={(event) => {
                                event.preventDefault();
                                put(route('configuracoes.whatsapp.update'), { preserveScroll: true });
                            }}
                            className="grid gap-4 p-4 sm:grid-cols-2"
                        >
                            <div className="grid gap-2">
                                <Label htmlFor="base_url">Endereço do servidor</Label>
                                <Input
                                    id="base_url"
                                    value={data.base_url}
                                    onChange={(e) => setData('base_url', e.target.value)}
                                    placeholder="https://evolution.suaempresa.com.br"
                                />
                                {errors.base_url && <p className="text-destructive text-xs font-medium">{errors.base_url}</p>}
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="instance">Nome da instância</Label>
                                <Input
                                    id="instance"
                                    value={data.instance}
                                    onChange={(e) => setData('instance', e.target.value)}
                                    placeholder="agencia-may"
                                />
                                {errors.instance && <p className="text-destructive text-xs font-medium">{errors.instance}</p>}
                            </div>

                            <div className="grid gap-2 sm:col-span-2">
                                <Label htmlFor="api_key">Chave de API</Label>
                                <Input
                                    id="api_key"
                                    type="password"
                                    autoComplete="off"
                                    value={data.api_key}
                                    onChange={(e) => setData('api_key', e.target.value)}
                                    placeholder={connection?.has_key ? 'Guardada — preencha só para trocar' : 'A chave global do seu servidor'}
                                />
                                {errors.api_key && <p className="text-destructive text-xs font-medium">{errors.api_key}</p>}
                                <p className="text-muted-foreground text-xs">Fica cifrada no banco e nunca volta para esta tela.</p>
                            </div>

                            <div className="flex items-center gap-2 sm:col-span-2">
                                <Button type="submit" loading={processing}>
                                    Salvar conexão
                                </Button>

                                {connection && (
                                    <>
                                        <Button type="button" variant="outline" onClick={gerarQr} disabled={carregando}>
                                            {carregando ? <Loader2 className="animate-spin" /> : <QrCode />}
                                            Gerar QR Code
                                        </Button>

                                        {conectado && (
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                className="text-destructive hover:text-destructive"
                                                onClick={() => setDesconectando(true)}
                                            >
                                                Desconectar
                                            </Button>
                                        )}
                                    </>
                                )}
                            </div>
                        </form>
                    </Card>

                    {aviso && <p className="text-muted-foreground text-sm">{aviso}</p>}

                    {qr && (
                        <Card>
                            <CardHeader>
                                <CardTitle>Leia com o WhatsApp do aparelho</CardTitle>
                                <CardDescription>
                                    No celular: Configurações → Aparelhos conectados → Conectar um aparelho. A tela avisa sozinha quando parear.
                                </CardDescription>
                            </CardHeader>

                            <CardContent className="flex justify-center pb-8">
                                {/* A Evolution devolve o QR já como imagem embutida. */}
                                <img src={qr} alt="QR Code para parear o WhatsApp" className="size-64 rounded-lg border bg-white p-2" />
                            </CardContent>
                        </Card>
                    )}
                </div>

                <ConfirmDialog
                    open={desconectando}
                    onOpenChange={setDesconectando}
                    title="Desconectar o aparelho?"
                    description="O sistema para de enviar mensagens até você parear de novo. A instância no servidor continua existindo."
                    confirmLabel="Desconectar"
                    onConfirm={() => {
                        router.delete(route('configuracoes.whatsapp.disconnect'), {
                            preserveScroll: true,
                            onFinish: () => {
                                setDesconectando(false);
                                setStatus('disconnected');
                            },
                        });
                    }}
                />
            </SettingsLayout>
        </AppLayout>
    );
}
