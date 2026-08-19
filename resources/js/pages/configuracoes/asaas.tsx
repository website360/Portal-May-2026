import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { SegmentedControl } from '@/components/ui/segmented-control';
import AppLayout from '@/layouts/app-layout';
import SettingsLayout from '@/layouts/settings/layout';
import { type BreadcrumbItem } from '@/types';
import { Head, router, useForm } from '@inertiajs/react';
import { Plug } from 'lucide-react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Configurações', href: '/configuracoes/perfil' },
    { title: 'Asaas', href: '/configuracoes/asaas' },
];

interface AsaasSettings {
    has_key: boolean;
    environment: 'production' | 'sandbox';
}

export default function Asaas({ settings }: { settings: AsaasSettings }) {
    const { data, setData, put, processing, errors } = useForm({
        api_key: '',
        environment: settings.environment,
    });

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Asaas" />

            <SettingsLayout>
                <div className="flex min-w-0 flex-col gap-6">
                    <div className="space-y-1">
                        <h2 className="text-lg font-semibold">Asaas</h2>
                        <p className="text-muted-foreground text-sm">
                            Conexão com o Asaas para conciliar as cobranças: conferir o que já foi pago e casar as cobranças dos dois lados.
                        </p>
                    </div>

                    <Card>
                        <form
                            onSubmit={(event) => {
                                event.preventDefault();
                                put(route('configuracoes.asaas.update'), { preserveScroll: true });
                            }}
                            className="grid gap-5 p-4"
                        >
                            <div className="grid gap-1.5">
                                <Label>Ambiente</Label>
                                <SegmentedControl
                                    aria-label="Ambiente"
                                    value={data.environment}
                                    onChange={(v) => setData('environment', v as 'production' | 'sandbox')}
                                    options={[
                                        { value: 'production', label: 'Produção' },
                                        { value: 'sandbox', label: 'Sandbox (teste)' },
                                    ]}
                                    className="sm:max-w-xs"
                                />
                            </div>

                            <div className="grid gap-1.5">
                                <Label htmlFor="api_key">Chave de API</Label>
                                <Input
                                    id="api_key"
                                    type="password"
                                    autoComplete="off"
                                    value={data.api_key}
                                    onChange={(e) => setData('api_key', e.target.value)}
                                    placeholder={settings.has_key ? 'Guardada — preencha só para trocar' : 'Cole aqui a chave de API do Asaas'}
                                />
                                {errors.api_key && <p className="text-destructive text-xs font-medium">{errors.api_key}</p>}
                                <p className="text-muted-foreground text-xs">
                                    {settings.has_key ? 'Uma chave já está salva. ' : ''}
                                    Fica no servidor, fora da web. No Asaas: Configurações → Integrações → Chave de API.
                                </p>
                            </div>

                            <div className="flex flex-wrap items-center gap-2">
                                <Button type="submit" loading={processing}>
                                    Salvar
                                </Button>

                                {settings.has_key && (
                                    <Button
                                        type="button"
                                        variant="outline"
                                        onClick={() => router.post(route('configuracoes.asaas.teste'), {}, { preserveScroll: true })}
                                    >
                                        <Plug />
                                        Testar conexão
                                    </Button>
                                )}
                            </div>
                        </form>
                    </Card>
                </div>
            </SettingsLayout>
        </AppLayout>
    );
}
