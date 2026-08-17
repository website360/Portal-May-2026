import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Sheet, SheetContent, SheetDescription, SheetFooter, SheetHeader, SheetTitle } from '@/components/ui/sheet';
import { cn } from '@/lib/utils';
import type { ManagedUser } from '@/pages/configuracoes/usuarios';
import { type ModuleKey, type PermissionLevel } from '@/types';
import { useForm } from '@inertiajs/react';
import { Eye, EyeOff, Pencil, ShieldCheck, UserRound } from 'lucide-react';
import { useEffect } from 'react';

const LEVELS: { value: PermissionLevel; label: string; icon: typeof Eye; hint: string }[] = [
    { value: 'none', label: 'Sem acesso', icon: EyeOff, hint: 'A página nem aparece no menu' },
    { value: 'read', label: 'Leitura', icon: Eye, hint: 'Vê tudo, não altera nada' },
    { value: 'write', label: 'Escrita', icon: Pencil, hint: 'Vê, cria, edita e exclui' },
];

interface UserFormSheetProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    /** null cria um usuário novo. */
    user: ManagedUser | null;
    modules: Record<ModuleKey, string>;
}

export function UserFormSheet({ open, onOpenChange, user, modules }: UserFormSheetProps) {
    const empty = Object.fromEntries(Object.keys(modules).map((key) => [key, 'none'])) as Record<ModuleKey, PermissionLevel>;

    const { data, setData, post, put, processing, errors, reset, clearErrors } = useForm({
        name: '',
        email: '',
        password: '',
        password_confirmation: '',
        role: 'member' as 'admin' | 'member',
        permissions: empty,
    });

    // Reabrir a gaveta em outro usuário precisa recarregar os campos.
    useEffect(() => {
        if (!open) {
            return;
        }

        clearErrors();

        setData({
            name: user?.name ?? '',
            email: user?.email ?? '',
            password: '',
            password_confirmation: '',
            role: user?.role ?? 'member',
            permissions: user?.role === 'admin' ? empty : (user?.permissions ?? empty),
        });
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open, user?.id]);

    function submit(event: React.FormEvent) {
        event.preventDefault();

        const options = {
            preserveScroll: true,
            onSuccess: () => {
                reset();
                onOpenChange(false);
            },
        };

        if (user) {
            put(route('configuracoes.usuarios.update', user.id), options);
        } else {
            post(route('configuracoes.usuarios.store'), options);
        }
    }

    const isAdmin = data.role === 'admin';

    return (
        <Sheet open={open} onOpenChange={onOpenChange}>
            <SheetContent className="flex w-full flex-col gap-0 p-0 sm:max-w-xl">
                <SheetHeader className="border-b px-6 py-4">
                    <SheetTitle>{user ? `Editar ${user.name}` : 'Novo usuário'}</SheetTitle>
                    <SheetDescription>
                        {user ? 'Altere os dados e o que essa pessoa alcança.' : 'Crie o acesso e escolha o que a pessoa alcança.'}
                    </SheetDescription>
                </SheetHeader>

                <form onSubmit={submit} className="flex min-h-0 flex-1 flex-col">
                    <div className="flex-1 space-y-6 overflow-y-auto px-6 py-5">
                        <div className="grid gap-4 sm:grid-cols-2">
                            <div className="grid gap-2">
                                <Label htmlFor="user-name">Nome</Label>
                                <Input
                                    id="user-name"
                                    autoFocus
                                    value={data.name}
                                    onChange={(e) => setData('name', e.target.value)}
                                    placeholder="Maria Souza"
                                />
                                {errors.name && <p className="text-destructive text-xs font-medium">{errors.name}</p>}
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="user-email">E-mail</Label>
                                <Input
                                    id="user-email"
                                    type="email"
                                    value={data.email}
                                    onChange={(e) => setData('email', e.target.value)}
                                    placeholder="maria@agenciamay.com.br"
                                />
                                {errors.email && <p className="text-destructive text-xs font-medium">{errors.email}</p>}
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="user-password">{user ? 'Nova senha' : 'Senha'}</Label>
                                <Input
                                    id="user-password"
                                    type="password"
                                    autoComplete="new-password"
                                    value={data.password}
                                    onChange={(e) => setData('password', e.target.value)}
                                    placeholder={user ? 'Deixe vazio para manter' : 'Mínimo de 8 caracteres'}
                                />
                                {errors.password && <p className="text-destructive text-xs font-medium">{errors.password}</p>}
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="user-password-confirm">Confirmar senha</Label>
                                <Input
                                    id="user-password-confirm"
                                    type="password"
                                    autoComplete="new-password"
                                    value={data.password_confirmation}
                                    onChange={(e) => setData('password_confirmation', e.target.value)}
                                    placeholder="Repita a senha"
                                />
                            </div>
                        </div>

                        <div className="grid gap-2">
                            <Label>Perfil de acesso</Label>

                            <div className="grid gap-2 sm:grid-cols-2">
                                <RoleOption
                                    active={!isAdmin}
                                    icon={UserRound}
                                    label="Comum"
                                    hint="Alcança só o que você marcar abaixo"
                                    onClick={() => setData('role', 'member')}
                                />
                                <RoleOption
                                    active={isAdmin}
                                    icon={ShieldCheck}
                                    label="Administrador"
                                    hint="Alcança tudo, inclusive esta tela"
                                    onClick={() => setData('role', 'admin')}
                                />
                            </div>

                            {errors.role && <p className="text-destructive text-xs font-medium">{errors.role}</p>}
                        </div>

                        <div className="grid gap-3">
                            <div className="flex items-baseline justify-between gap-3">
                                <Label>Permissões por página</Label>

                                {!isAdmin && (
                                    <div className="flex gap-2">
                                        <button
                                            type="button"
                                            className="text-muted-foreground hover:text-foreground text-xs"
                                            onClick={() =>
                                                setData(
                                                    'permissions',
                                                    Object.fromEntries(Object.keys(modules).map((k) => [k, 'write'])) as Record<
                                                        ModuleKey,
                                                        PermissionLevel
                                                    >,
                                                )
                                            }
                                        >
                                            Marcar tudo
                                        </button>
                                        <span className="text-muted-foreground text-xs">·</span>
                                        <button
                                            type="button"
                                            className="text-muted-foreground hover:text-foreground text-xs"
                                            onClick={() =>
                                                setData(
                                                    'permissions',
                                                    Object.fromEntries(Object.keys(modules).map((k) => [k, 'none'])) as Record<
                                                        ModuleKey,
                                                        PermissionLevel
                                                    >,
                                                )
                                            }
                                        >
                                            Limpar
                                        </button>
                                    </div>
                                )}
                            </div>

                            {isAdmin ? (
                                <p className="text-muted-foreground bg-muted/50 rounded-lg border border-dashed px-4 py-6 text-center text-sm">
                                    Administrador alcança todas as páginas. Não há o que marcar.
                                </p>
                            ) : (
                                <div className="overflow-hidden rounded-lg border">
                                    {(Object.keys(modules) as ModuleKey[]).map((key, index) => (
                                        <div
                                            key={key}
                                            className={cn('flex flex-wrap items-center justify-between gap-3 px-4 py-2.5', index > 0 && 'border-t')}
                                        >
                                            <span className="text-sm font-medium">{modules[key]}</span>

                                            <div className="flex gap-1">
                                                {LEVELS.map((level) => (
                                                    <button
                                                        key={level.value}
                                                        type="button"
                                                        title={level.hint}
                                                        aria-pressed={data.permissions[key] === level.value}
                                                        onClick={() => setData('permissions', { ...data.permissions, [key]: level.value })}
                                                        className={cn(
                                                            'flex items-center gap-1.5 rounded-md border px-2.5 py-1 text-xs transition-all',
                                                            'focus-visible:ring-primary/20 focus-visible:ring-2 focus-visible:outline-hidden',
                                                            data.permissions[key] === level.value
                                                                ? 'border-primary bg-accent text-accent-foreground font-medium'
                                                                : 'border-input text-muted-foreground hover:border-primary/30',
                                                        )}
                                                    >
                                                        <level.icon className="size-3.5" />
                                                        {level.label}
                                                    </button>
                                                ))}
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            )}

                            <p className="text-muted-foreground text-xs">
                                Sem acesso esconde a página do menu. Leitura mostra tudo, mas nenhum botão que grava funciona.
                            </p>
                        </div>
                    </div>

                    <SheetFooter className="flex-row justify-end gap-2 border-t px-6 py-4">
                        <Button type="button" variant="ghost" onClick={() => onOpenChange(false)}>
                            Cancelar
                        </Button>
                        <Button type="submit" loading={processing} disabled={data.name.trim() === ''}>
                            {user ? 'Salvar' : 'Criar usuário'}
                        </Button>
                    </SheetFooter>
                </form>
            </SheetContent>
        </Sheet>
    );
}

function RoleOption({
    active,
    icon: Icon,
    label,
    hint,
    onClick,
}: {
    active: boolean;
    icon: typeof UserRound;
    label: string;
    hint: string;
    onClick: () => void;
}) {
    return (
        <button
            type="button"
            onClick={onClick}
            className={cn(
                'flex items-start gap-2.5 rounded-lg border px-3 py-2.5 text-left transition-all duration-150',
                'focus-visible:ring-primary/20 focus-visible:ring-2 focus-visible:outline-hidden active:scale-[0.98]',
                active ? 'border-primary bg-accent text-accent-foreground shadow-xs' : 'border-input bg-background hover:border-primary/30',
            )}
        >
            <Icon className="mt-0.5 size-4 shrink-0" />
            <span className="min-w-0">
                <span className="block text-sm font-medium">{label}</span>
                <span className="text-muted-foreground block text-xs">{hint}</span>
            </span>
        </button>
    );
}
