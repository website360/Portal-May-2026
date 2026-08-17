import { Pagination } from '@/components/pagination';
import { TaskFormSheet } from '@/components/tasks/task-form-sheet';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Combobox } from '@/components/ui/combobox';
import { ConfirmDialog } from '@/components/ui/confirm-dialog';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { FilterChip } from '@/components/ui/filter-chip';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { StatusPicker } from '@/components/ui/status-picker';
import { taskPriority, taskStatusOptions, toneFor } from '@/config/domain';
import AppLayout from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
import { type BreadcrumbItem, type Paginated } from '@/types';
import type { Task, TaskFilters, TaskStats } from '@/types/tasks';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { ArrowDown, ArrowUp, CalendarDays, CircleDot, Clock, ListTodo, MoreHorizontal, Pencil, Plus, Search, Trash2, UserRound } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Tarefas', href: '/tarefas' },
];

interface Option {
    id: number;
    name: string;
}

interface TarefasPageProps {
    tasks: Paginated<Task>;
    filters: TaskFilters;
    stats: TaskStats;
    clients: Option[];
    users: Option[];
}

export default function Tarefas({ tasks, filters, stats, clients, users }: TarefasPageProps) {
    const [search, setSearch] = useState(filters.search);
    const [formOpen, setFormOpen] = useState(false);
    const [editing, setEditing] = useState<Task | null>(null);
    const firstRender = useRef(true);

    useEffect(() => {
        if (firstRender.current) {
            firstRender.current = false;
            return;
        }

        const timer = setTimeout(() => apply({ search }), 350);

        return () => clearTimeout(timer);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [search]);

    function apply(overrides: Partial<TaskFilters>) {
        const next = {
            search,
            status: filters.status,
            mine: filters.mine,
            overdue: filters.overdue,
            done_today: filters.done_today,
            sort: filters.sort,
            direction: filters.direction,
            ...overrides,
        };

        router.get(route('tarefas.index'), next, { preserveState: true, preserveScroll: true, replace: true });
    }

    /**
     * "Atrasadas" e "Concluídas hoje" são recortes por prazo, e situação já está
     * embutida em cada um. Escolher qualquer um deles limpa os outros, senão a
     * combinação devolveria lista vazia sem explicar por quê.
     */
    function selectView(view: 'status' | 'overdue' | 'done_today', value: string | boolean) {
        apply({
            status: view === 'status' ? (value as string) : '',
            overdue: view === 'overdue' ? (value as boolean) : false,
            done_today: view === 'done_today' ? (value as boolean) : false,
        });
    }

    const hasFilters = Boolean(filters.search || filters.status || filters.mine || filters.overdue || filters.done_today);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Tarefas" />

            <div className="animate-fade-in flex min-w-0 flex-1 flex-col gap-6 p-6">
                <div className="space-y-1">
                    <h1 className="text-2xl font-bold tracking-tight">Tarefas</h1>
                    <p className="text-muted-foreground text-sm">O que precisa ser feito hoje na agência.</p>
                </div>

                <QuickAdd users={users} />

                <div className="flex flex-wrap gap-2">
                    <FilterChip
                        icon={ListTodo}
                        label="A fazer"
                        count={stats.pending}
                        active={filters.status === 'pending'}
                        onClick={() => selectView('status', filters.status === 'pending' ? '' : 'pending')}
                    />
                    <FilterChip
                        icon={CircleDot}
                        label="Em andamento"
                        count={stats.doing}
                        active={filters.status === 'doing'}
                        onClick={() => selectView('status', filters.status === 'doing' ? '' : 'doing')}
                    />
                    <FilterChip
                        icon={Clock}
                        label="Atrasadas"
                        count={stats.overdue}
                        tone="destructive"
                        active={filters.overdue}
                        onClick={() => selectView('overdue', !filters.overdue)}
                    />
                    <FilterChip
                        icon={UserRound}
                        label="Minhas em aberto"
                        count={stats.mine}
                        active={filters.mine}
                        onClick={() => apply({ mine: !filters.mine })}
                    />
                    <FilterChip
                        icon={CalendarDays}
                        label="Concluídas hoje"
                        count={stats.doneToday}
                        tone="success"
                        active={filters.done_today}
                        onClick={() => selectView('done_today', !filters.done_today)}
                    />
                </div>

                <Card>
                    <div className="flex flex-wrap items-center gap-3 p-4">
                        <div className="min-w-56 flex-1">
                            <Input
                                startIcon={Search}
                                value={search}
                                onChange={(e) => setSearch(e.target.value)}
                                placeholder="Buscar tarefa, detalhe ou cliente…"
                            />
                        </div>

                        {/* Lista não tem cabeçalho para clicar, então a ordenação vira seletor. */}
                        <Select value={filters.sort} onValueChange={(value) => apply({ sort: value, direction: 'asc' })}>
                            <SelectTrigger className="w-48" aria-label="Ordenar por">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="work">Ordem de trabalho</SelectItem>
                                <SelectItem value="due_date">Prazo</SelectItem>
                                <SelectItem value="priority">Prioridade</SelectItem>
                                <SelectItem value="status">Situação</SelectItem>
                                <SelectItem value="client">Cliente</SelectItem>
                                <SelectItem value="owner">Responsável</SelectItem>
                                <SelectItem value="title">Título</SelectItem>
                                <SelectItem value="created_at">Criação</SelectItem>
                            </SelectContent>
                        </Select>

                        {filters.sort !== 'work' && (
                            <Button
                                variant="outline"
                                size="icon"
                                aria-label={filters.direction === 'asc' ? 'Ordem crescente' : 'Ordem decrescente'}
                                title={filters.direction === 'asc' ? 'Crescente' : 'Decrescente'}
                                onClick={() => apply({ direction: filters.direction === 'asc' ? 'desc' : 'asc' })}
                            >
                                {filters.direction === 'asc' ? <ArrowUp /> : <ArrowDown />}
                            </Button>
                        )}

                        {hasFilters && (
                            <Button
                                variant="ghost"
                                size="sm"
                                onClick={() => {
                                    setSearch('');
                                    apply({ search: '', status: '', mine: false, overdue: false, done_today: false });
                                }}
                            >
                                Limpar filtros
                            </Button>
                        )}
                    </div>

                    {tasks.data.length === 0 ? (
                        <div className="flex flex-col items-center gap-3 border-t px-6 py-16 text-center">
                            <span className="bg-accent text-accent-foreground flex size-11 items-center justify-center rounded-xl">
                                <ListTodo className="size-5" />
                            </span>
                            <p className="text-sm font-medium">{hasFilters ? 'Nada por aqui' : 'Tudo em dia'}</p>
                            <p className="text-muted-foreground text-sm">
                                {hasFilters ? 'Nenhuma tarefa com esses filtros.' : 'Nenhuma tarefa pendente na agência.'}
                            </p>
                        </div>
                    ) : (
                        <>
                            <ul className="border-t">
                                {tasks.data.map((task) => (
                                    <TaskRow
                                        key={task.id}
                                        task={task}
                                        onEdit={() => {
                                            setEditing(task);
                                            setFormOpen(true);
                                        }}
                                    />
                                ))}
                            </ul>

                            <Pagination page={tasks} />
                        </>
                    )}
                </Card>
            </div>

            <TaskFormSheet
                open={formOpen}
                onOpenChange={(open) => {
                    setFormOpen(open);
                    if (!open) setEditing(null);
                }}
                task={editing}
                clients={clients}
                users={users}
            />
        </AppLayout>
    );
}

/**
 * Criar tarefa sem abrir formulário: digitar e apertar Enter. Responsável,
 * prioridade e prazo ficam ao lado para não obrigar a reabrir a tarefa depois;
 * a situação não aparece porque toda tarefa nova nasce em "A fazer".
 */
function QuickAdd({ users }: { users: Option[] }) {
    const { data, setData, post, processing, errors, reset } = useForm({
        title: '',
        user_id: '',
        priority: 'normal',
        due_date: '',
    });
    const inputRef = useRef<HTMLInputElement>(null);

    function submit(event: React.FormEvent) {
        event.preventDefault();
        if (data.title.trim() === '') return;

        post(route('tarefas.store'), {
            preserveScroll: true,
            onSuccess: () => {
                // Zera só o título: quem cadastra em série costuma manter
                // responsável e prioridade da vez.
                reset('title');
                inputRef.current?.focus();
            },
        });
    }

    return (
        <Card>
            <form onSubmit={submit} className="flex flex-wrap items-end gap-3 p-4">
                <div className="min-w-64 flex-1">
                    <Input
                        ref={inputRef}
                        startIcon={Plus}
                        value={data.title}
                        onChange={(e) => setData('title', e.target.value)}
                        placeholder="O que precisa ser feito?"
                        aria-label="Nova tarefa"
                    />
                    {errors.title && <p className="text-destructive mt-1.5 text-xs font-medium">{errors.title}</p>}
                </div>

                <div className="w-44">
                    <Combobox
                        value={data.user_id}
                        onChange={(value) => setData('user_id', value)}
                        options={users.map((user) => ({ value: String(user.id), label: user.name }))}
                        placeholder="Responsável"
                        searchPlaceholder="Buscar pessoa…"
                        emptyText="Ninguém com esse nome."
                        clearable
                        clearLabel="Ninguém"
                    />
                </div>

                <div className="w-36">
                    <Select value={data.priority} onValueChange={(value) => setData('priority', value)}>
                        <SelectTrigger aria-label="Prioridade">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            {Object.entries(taskPriority).map(([value, tone]) => (
                                <SelectItem key={value} value={value}>
                                    {tone.label}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </div>

                <div className="w-40">
                    <Input type="date" aria-label="Prazo" value={data.due_date} onChange={(e) => setData('due_date', e.target.value)} />
                </div>

                <Button type="submit" loading={processing} disabled={data.title.trim() === ''}>
                    Adicionar
                </Button>
            </form>
        </Card>
    );
}

function TaskRow({ task, onEdit }: { task: Task; onEdit: () => void }) {
    const [confirming, setConfirming] = useState(false);
    const done = task.status === 'done';
    const priority = toneFor(taskPriority, task.priority);

    /** A caixa marca e desmarca pelo mesmo endpoint do seletor de situação. */
    function toggle() {
        router.patch(route('tarefas.status', task.id), { status: done ? 'pending' : 'done' }, { preserveScroll: true, preserveState: true });
    }

    /** Controles que agem sozinhos não podem disparar a abertura da tarefa. */
    const stop = (event: React.MouseEvent) => event.stopPropagation();

    return (
        <li
            onClick={onEdit}
            className="hover:bg-muted/40 flex cursor-pointer flex-wrap items-center gap-x-4 gap-y-2 border-b px-6 py-3 transition-colors last:border-b-0"
        >
            <span onClick={stop}>
                <Checkbox checked={done} onCheckedChange={toggle} aria-label={done ? `Reabrir ${task.title}` : `Concluir ${task.title}`} />
            </span>

            <div className="min-w-48 flex-1">
                {/* Botão de verdade para quem navega por teclado; o clique na linha faz o mesmo. */}
                <button type="button" onClick={onEdit} className="hover:text-primary block text-left">
                    <span className={cn('text-sm font-medium', done && 'text-muted-foreground line-through')}>{task.title}</span>
                </button>

                <div className="text-muted-foreground mt-0.5 flex flex-wrap items-center gap-x-2 gap-y-0.5 text-xs">
                    {task.description && <span className="max-w-96 truncate">{task.description}</span>}

                    {task.client && (
                        <Link href={route('clientes.show', task.client.id)} onClick={stop} className="hover:text-primary">
                            {task.client.name}
                        </Link>
                    )}

                    {task.project && <span>· {task.project.name}</span>}
                </div>
            </div>

            {task.priority !== 'normal' && <Badge variant={priority.variant}>{priority.label}</Badge>}

            <span className={cn('tabular w-24 text-xs', task.is_overdue ? 'text-destructive font-medium' : 'text-muted-foreground')}>
                {task.due_date_label ?? '—'}
            </span>

            <span className="text-muted-foreground w-32 truncate text-xs">{task.user?.name ?? 'Sem responsável'}</span>

            <span onClick={stop}>
                <StatusPicker
                    value={task.status}
                    options={taskStatusOptions}
                    url={route('tarefas.status', task.id)}
                    field="status"
                    label="situação"
                />
            </span>

            <span onClick={stop}>
                <DropdownMenu>
                    <DropdownMenuTrigger asChild>
                        <Button variant="ghost" size="icon-sm" aria-label={`Ações de ${task.title}`}>
                            <MoreHorizontal />
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end">
                        <DropdownMenuItem onSelect={onEdit}>
                            <Pencil className="size-4" />
                            Editar
                        </DropdownMenuItem>
                        <DropdownMenuItem
                            // Confirmar antes: a exclusão não tem volta, e o
                            // item fica ao lado de "Editar" no mesmo menu.
                            onSelect={() => setConfirming(true)}
                            className="text-destructive focus:text-destructive"
                        >
                            <Trash2 className="size-4" />
                            Excluir
                        </DropdownMenuItem>
                    </DropdownMenuContent>
                </DropdownMenu>

                <ConfirmDialog
                    open={confirming}
                    onOpenChange={setConfirming}
                    title={`Excluir "${task.title}"?`}
                    description="A tarefa sai da lista para sempre."
                    onConfirm={() =>
                        router.delete(route('tarefas.destroy', task.id), {
                            preserveScroll: true,
                            onFinish: () => setConfirming(false),
                        })
                    }
                />
            </span>
        </li>
    );
}
