import { Head, useForm } from '@inertiajs/react';
import { Lock, LogIn, Mail } from 'lucide-react';
import { FormEventHandler } from 'react';

import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AuthLayout from '@/layouts/auth-layout';

interface LoginForm {
    email: string;
    password: string;
    remember: boolean;
    [key: string]: string | boolean;
}

export default function Login({ status }: { status?: string }) {
    const { data, setData, post, processing, errors, reset } = useForm<LoginForm>({
        email: '',
        password: '',
        remember: false,
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('login'), {
            onFinish: () => reset('password'),
        });
    };

    return (
        <AuthLayout title="Entrar no Sistema May" description="Use suas credenciais da agência para continuar">
            <Head title="Entrar" />

            {status && (
                <div className="border-success/20 bg-success/10 text-success mb-6 rounded-lg border px-3 py-2 text-center text-sm font-medium">
                    {status}
                </div>
            )}

            <form className="flex flex-col gap-5" onSubmit={submit}>
                <div className="grid gap-2">
                    <Label htmlFor="email">E-mail</Label>
                    <Input
                        id="email"
                        type="email"
                        name="email"
                        required
                        autoFocus
                        tabIndex={1}
                        autoComplete="email"
                        startIcon={Mail}
                        value={data.email}
                        onChange={(e) => setData('email', e.target.value)}
                        placeholder="voce@agenciamay.com.br"
                    />
                    <InputError message={errors.email} />
                </div>

                <div className="grid gap-2">
                    <Label htmlFor="password">Senha</Label>
                    <Input
                        id="password"
                        type="password"
                        name="password"
                        required
                        tabIndex={2}
                        autoComplete="current-password"
                        startIcon={Lock}
                        value={data.password}
                        onChange={(e) => setData('password', e.target.value)}
                        placeholder="••••••••"
                    />
                    <InputError message={errors.password} />
                </div>

                <div className="flex items-center gap-2.5">
                    <Checkbox
                        id="remember"
                        tabIndex={3}
                        checked={data.remember}
                        onCheckedChange={(checked) => setData('remember', checked === true)}
                    />
                    <Label htmlFor="remember" className="text-muted-foreground text-sm font-normal">
                        Manter-me conectado
                    </Label>
                </div>

                <Button type="submit" size="lg" className="mt-1 w-full" tabIndex={4} loading={processing}>
                    {!processing && <LogIn />}
                    Entrar
                </Button>
            </form>

            <p className="text-muted-foreground mt-6 text-center text-xs">Não tem acesso? Fale com o administrador do sistema.</p>
        </AuthLayout>
    );
}
