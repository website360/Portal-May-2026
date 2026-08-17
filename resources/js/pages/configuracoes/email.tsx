import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { SegmentedControl } from '@/components/ui/segmented-control';
import AppLayout from '@/layouts/app-layout';
import SettingsLayout from '@/layouts/settings/layout';
import { type BreadcrumbItem } from '@/types';
import { Head, router, useForm } from '@inertiajs/react';
import { CircleCheck, Send, TriangleAlert } from 'lucide-react';
import { useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Configurações', href: '/configuracoes/perfil' },
    { title: 'E-mail', href: '/configuracoes/email' },
];

interface Settings {
    host: string;
    port: number;
    username: string | null;
    has_password: boolean;
    encryption: string | null;
    from_address: string;
    from_name: string;
    active: boolean;
    tested_at: string | null;
    test_error: string | null;
}

/** As portas que os provedores usam, e o que cada uma quer de criptografia. */
const PORTAS: Record<string, { port: string; encryption: string }> = {
    tls: { port: '587', encryption: 'tls' },
    ssl: { port: '465', encryption: 'ssl' },
};

export default function Email({ settings }: { settings: Settings | null }) {
    const [testando, setTestando] = useState(false);

    const { data, setData, put, processing, errors } = useForm({
        host: settings?.host ?? '',
        port: String(settings?.port ?? 587),
        username: settings?.username ?? '',
        password: '',
        encryption: settings?.encryption ?? 'tls',
        from_address: settings?.from_address ?? '',
        from_name: settings?.from_name ?? 'Agência May',
        active: settings?.active ?? true,
    });

    /** Trocar a criptografia ajusta a porta: 587 e 465 andam com elas. */
    function trocarCriptografia(valor: string) {
        setData((atual) => ({ ...atual, encryption: valor, port: PORTAS[valor]?.port ?? atual.port }));
    }

    function salvar(e: React.FormEvent) {
        e.preventDefault();
        put(route('configuracoes.email.update'), { preserveScroll: true });
    }

    function testar() {
        setTestando(true);
        router.post(route('configuracoes.email.teste'), {}, { preserveScroll: true, onFinish: () => setTestando(false) });
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="E-mail" />

            <SettingsLayout>
                <div className="flex min-w-0 flex-col gap-6">
                    <div className="space-y-1">
                        <h2 className="text-lg font-semibold">Servidor de e-mail</h2>
                        <p className="text-muted-foreground text-sm">
                            Por onde saem os e-mails do sistema — os avisos dos modelos de mensagem e a recuperação de senha.
                        </p>
                    </div>

                    {settings && (
                        <Card>
                            <CardContent className="flex flex-wrap items-start justify-between gap-3 p-4">
                                <div className="flex items-start gap-2">
                                    {settings.tested_at ? (
                                        <CircleCheck className="text-success mt-0.5 size-4 shrink-0" />
                                    ) : (
                                        <TriangleAlert className="text-warning mt-0.5 size-4 shrink-0" />
                                    )}

                                    <div className="space-y-0.5">
                                        <p className="text-sm font-medium">
                                            {settings.tested_at ? `Testado em ${settings.tested_at}` : 'Ainda não testado'}
                                        </p>
                                        <p className="text-muted-foreground text-xs">
                                            {settings.test_error ??
                                                'O teste manda um e-mail para o seu próprio endereço. É a única forma de saber que a senha está certa antes de um cliente deixar de receber.'}
                                        </p>
                                    </div>
                                </div>

                                <Button variant="outline" onClick={testar} disabled={testando}>
                                    <Send />
                                    {testando ? 'Enviando…' : 'Enviar teste'}
                                </Button>
                            </CardContent>
                        </Card>
                    )}

                    <Card>
                        <CardHeader>
                            <CardTitle>Conexão</CardTitle>
                            <CardDescription>Os dados de SMTP do seu provedor de e-mail.</CardDescription>
                        </CardHeader>

                        <CardContent>
                            <form onSubmit={salvar} className="grid gap-5">
                                <div className="grid content-start gap-5 sm:grid-cols-[minmax(0,1fr)_minmax(0,10rem)]">
                                    <Campo label="Servidor" erro={errors.host}>
                                        <Input
                                            value={data.host}
                                            onChange={(e) => setData('host', e.target.value)}
                                            placeholder="smtp.seuprovedor.com.br"
                                        />
                                    </Campo>

                                    <Campo label="Porta" erro={errors.port}>
                                        <Input
                                            inputMode="numeric"
                                            value={data.port}
                                            onChange={(e) => setData('port', e.target.value.replace(/\D/g, ''))}
                                        />
                                    </Campo>
                                </div>

                                <Campo label="Criptografia" dica="TLS na porta 587 é o mais comum.">
                                    <SegmentedControl
                                        className="max-w-xs"
                                        aria-label="Criptografia"
                                        value={data.encryption}
                                        onChange={trocarCriptografia}
                                        options={[
                                            { value: 'tls', label: 'TLS' },
                                            { value: 'ssl', label: 'SSL' },
                                        ]}
                                    />
                                </Campo>

                                <div className="grid content-start gap-5 sm:grid-cols-2">
                                    <Campo label="Usuário" erro={errors.username}>
                                        <Input
                                            value={data.username}
                                            onChange={(e) => setData('username', e.target.value)}
                                            placeholder="contato@agenciamay.com.br"
                                        />
                                    </Campo>

                                    <Campo
                                        label="Senha"
                                        erro={errors.password}
                                        dica={settings?.has_password ? 'Já existe uma senha salva. Deixe em branco para mantê-la.' : undefined}
                                    >
                                        <Input
                                            type="password"
                                            autoComplete="new-password"
                                            value={data.password}
                                            onChange={(e) => setData('password', e.target.value)}
                                            placeholder={settings?.has_password ? '••••••••' : ''}
                                        />
                                    </Campo>
                                </div>

                                <div className="grid content-start gap-5 sm:grid-cols-2">
                                    <Campo label="Enviar como" erro={errors.from_address} dica="O endereço que o cliente vê no remetente.">
                                        <Input
                                            type="email"
                                            value={data.from_address}
                                            onChange={(e) => setData('from_address', e.target.value)}
                                            placeholder="contato@agenciamay.com.br"
                                        />
                                    </Campo>

                                    <Campo label="Nome de quem envia" erro={errors.from_name}>
                                        <Input value={data.from_name} onChange={(e) => setData('from_name', e.target.value)} />
                                    </Campo>
                                </div>

                                <label className="flex cursor-pointer items-start gap-2">
                                    <Checkbox
                                        className="mt-0.5"
                                        checked={data.active}
                                        onCheckedChange={(v) => setData('active', v === true)}
                                    />
                                    <span className="space-y-0.5">
                                        <span className="block text-sm">Usar este servidor</span>
                                        <span className="text-muted-foreground block text-xs">
                                            Desmarcado, o sistema não manda e-mail nenhum — os modelos com canal de e-mail param de sair.
                                        </span>
                                    </span>
                                </label>

                                <div className="flex justify-end">
                                    <Button type="submit" disabled={processing}>
                                        Salvar
                                    </Button>
                                </div>
                            </form>
                        </CardContent>
                    </Card>
                </div>
            </SettingsLayout>
        </AppLayout>
    );
}

function Campo({ label, dica, erro, children }: { label: string; dica?: string; erro?: string; children: React.ReactNode }) {
    return (
        <div className="grid content-start gap-1.5">
            <Label>{label}</Label>
            {children}
            {erro ? <p className="text-destructive text-xs">{erro}</p> : dica && <p className="text-muted-foreground text-xs">{dica}</p>}
        </div>
    );
}
