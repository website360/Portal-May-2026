import { ListSortSelect, type ListSorting } from '@/components/settings/list-sort-select';
import { UserFormSheet } from '@/components/settings/user-form-sheet';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Dialog, DialogClose, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { useInitials } from '@/hooks/use-initials';
import AppLayout from '@/layouts/app-layout';
import SettingsLayout from '@/layouts/settings/layout';
import { formatNumber } from '@/lib/format';
import { cn } from '@/lib/utils';
import { type BreadcrumbItem, type ModuleKey, type PermissionLevel } from '@/types';
import { Head, router, usePage } from '@inertiajs/react';
import { Pencil, Plus, ShieldCheck, Trash2 } from 'lucide-react';
import { useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Configurações', href: '/configuracoes/perfil' },
    { title: 'Usuários', href: '/configuracoes/usuarios' },
];

export interface ManagedUser {
    id: number;
    name: string;
    email: string;
    avatar: string | null;
    role: 'admin' | 'member';
    permissions: Record<ModuleKey, PermissionLevel>;
    tasks_count: number;
    is_me: boolean;
}

interface UsuariosPageProps {
    users: ManagedUser[];
    modules: Record<ModuleKey, string>;
    filters: ListSorting;
}

/** Rotulo curto do nivel, para caber no resumo de cada linha. */
export const levelLabel: Record<PermissionLevel, string> = {
    none: 'Sem acesso',
    read: 'Leitura',
    write: 'Leitura e escrita',
};

export default function Usuarios({ users, modules, filters }: UsuariosPageProps) {
    const [formOpen, setFormOpen] = useState(false);
    const [editing, setEditing] = useState<ManagedUser | null>(null);
    const [deleting, setDeleting] = useState<ManagedUser | null>(null);
    const { errors } = usePage().props as { errors: Record<string, string> };

    function openCreate() {
        setEditing(null);
        setFormOpen(true);
    }

    const admins = users.filter((user) => user.role === 'admin').length;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Usuários" />

            <SettingsLayout>
                <div className="flex min-w-0 flex-col gap-6">
                    <div className="flex flex-wrap items-start justify-between gap-3">
                        <div className="space-y-1">
                            <h2 className="text-lg font-semibold">Usuários</h2>
                            <p className="text-muted-foreground text-sm">
                                Quem entra no sistema e o que cada um pode ver e mexer, página por página.
                            </p>
                        </div>

                        <div className="flex items-center gap-2">
                            <ListSortSelect
                                url={route('configuracoes.usuarios.index')}
                                filters={filters}
                                options={[
                                    { value: 'name', label: 'Nome' },
                                    { value: 'email', label: 'E-mail' },
                                    { value: 'role', label: 'Perfil de acesso' },
                                    { value: 'created_at', label: 'Criação' },
                                ]}
                            />

                            <Button onClick={openCreate}>
                                <Plus />
                                Novo usuário
                            </Button>
                        </div>
                    </div>

                    {errors.usuario && <p className="text-destructive text-sm font-medium">{errors.usuario}</p>}

                    <Card>
                        <CardHeader>
                            <CardTitle>Cadastrados</CardTitle>
                            <CardDescription>
                                {formatNumber(users.length)} {users.length === 1 ? 'usuário' : 'usuários'} · {formatNumber(admins)}{' '}
                                {admins === 1 ? 'administrador' : 'administradores'}
                            </CardDescription>
                        </CardHeader>

                        <ul className="border-t">
                            {users.map((user) => (
                                <UserRow
                                    key={user.id}
                                    user={user}
                                    modules={modules}
                                    onEdit={() => {
                                        setEditing(user);
                                        setFormOpen(true);
                                    }}
                                    onDelete={() => setDeleting(user)}
                                />
                            ))}
                        </ul>
                    </Card>
                </div>

                <UserFormSheet open={formOpen} onOpenChange={setFormOpen} user={editing} modules={modules} />

                <Dialog open={deleting !== null} onOpenChange={(open) => !open && setDeleting(null)}>
                    <DialogContent>
                        <DialogHeader>
                            <DialogTitle>Excluir {deleting?.name}?</DialogTitle>
                            <DialogDescription>
                                A pessoa perde o acesso ao sistema. As tarefas dela continuam registradas, só ficam sem responsável.
                            </DialogDescription>
                        </DialogHeader>

                        <DialogFooter>
                            <DialogClose asChild>
                                <Button variant="secondary">Cancelar</Button>
                            </DialogClose>

                            <Button
                                variant="destructive"
                                onClick={() => {
                                    router.delete(route('configuracoes.usuarios.destroy', deleting!.id), {
                                        preserveScroll: true,
                                        onFinish: () => setDeleting(null),
                                    });
                                }}
                            >
                                Excluir
                            </Button>
                        </DialogFooter>
                    </DialogContent>
                </Dialog>
            </SettingsLayout>
        </AppLayout>
    );
}

function UserRow({
    user,
    modules,
    onEdit,
    onDelete,
}: {
    user: ManagedUser;
    modules: Record<ModuleKey, string>;
    onEdit: () => void;
    onDelete: () => void;
}) {
    const getInitials = useInitials();

    // Resumo do que a pessoa alcanca, para nao precisar abrir o cadastro so
    // para lembrar quem ve o que.
    const granted = (Object.keys(modules) as ModuleKey[]).filter((key) => user.permissions[key] !== 'none');

    return (
        <li className="hover:bg-muted/40 flex flex-wrap items-center gap-x-4 gap-y-3 border-b px-6 py-3 transition-colors last:border-b-0">
            <Avatar className="size-9 shrink-0 border text-xs">
                {user.avatar && <AvatarImage src={user.avatar} alt={user.name} className="object-cover" />}
                <AvatarFallback className="bg-neutral-200 font-semibold text-black dark:bg-neutral-700 dark:text-white">
                    {getInitials(user.name)}
                </AvatarFallback>
            </Avatar>

            <div className="min-w-48 flex-1">
                <div className="flex flex-wrap items-center gap-2">
                    <span className="text-sm font-medium">{user.name}</span>

                    {user.role === 'admin' && (
                        <Badge variant="secondary" className="gap-1">
                            <ShieldCheck className="size-3" />
                            Administrador
                        </Badge>
                    )}

                    {user.is_me && <Badge variant="muted">Você</Badge>}
                </div>

                <p className="text-muted-foreground truncate text-xs">{user.email}</p>
            </div>

            <div className="min-w-52 flex-1">
                {user.role === 'admin' ? (
                    <p className="text-muted-foreground text-xs">Acesso total a todas as páginas.</p>
                ) : granted.length === 0 ? (
                    <p className="text-muted-foreground text-xs">Sem acesso a nenhuma página.</p>
                ) : (
                    <div className="flex flex-wrap gap-1">
                        {granted.map((key) => (
                            <span
                                key={key}
                                className={cn(
                                    'rounded border px-1.5 py-0.5 text-[0.7rem]',
                                    user.permissions[key] === 'write'
                                        ? 'border-primary/30 bg-primary/5 text-primary'
                                        : 'text-muted-foreground border-input',
                                )}
                                title={levelLabel[user.permissions[key]]}
                            >
                                {modules[key]}
                                {user.permissions[key] === 'read' && ' (leitura)'}
                            </span>
                        ))}
                    </div>
                )}
            </div>

            <span className="text-muted-foreground tabular shrink-0 text-xs">
                {user.tasks_count === 0 ? 'sem tarefas' : `${formatNumber(user.tasks_count)} ${user.tasks_count === 1 ? 'tarefa' : 'tarefas'}`}
            </span>

            <div className="flex shrink-0 items-center gap-1">
                <Button variant="ghost" size="icon-sm" onClick={onEdit} aria-label={`Editar ${user.name}`}>
                    <Pencil />
                </Button>
                <Button
                    variant="ghost"
                    size="icon-sm"
                    onClick={onDelete}
                    disabled={user.is_me}
                    title={user.is_me ? 'Você não pode excluir a própria conta' : `Excluir ${user.name}`}
                    aria-label={`Excluir ${user.name}`}
                    className="text-destructive hover:text-destructive"
                >
                    <Trash2 />
                </Button>
            </div>
        </li>
    );
}
