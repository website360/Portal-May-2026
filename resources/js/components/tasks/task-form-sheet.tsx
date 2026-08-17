import { Button } from '@/components/ui/button';
import { Combobox } from '@/components/ui/combobox';
import { Input } from '@/components/ui/input';
import { SegmentedControl } from '@/components/ui/segmented-control';
import { Sheet, SheetContent, SheetDescription, SheetHeader, SheetTitle } from '@/components/ui/sheet';
import { Textarea } from '@/components/ui/textarea';
import { taskPrioritySegments, taskStatusSegments } from '@/config/domain';
import { cn } from '@/lib/utils';
import { EMPTY_TASK_FORM, toTaskFormData, type Task, type TaskFormData, type TaskPriority, type TaskStatus } from '@/types/tasks';
import { useForm } from '@inertiajs/react';
import { Building2, CalendarDays, Check, CircleDot, Flag, Trash2, UserRound } from 'lucide-react';
import { useEffect } from 'react';

interface Option {
    id: number;
    name: string;
    /** Termos que encontram a opção sem aparecer nela — razão social, documento. */
    search?: string;
}

interface TaskFormSheetProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    task: Task | null;
    clients: Option[];
    users: Option[];
}

export function TaskFormSheet({ open, onOpenChange, task, clients, users }: TaskFormSheetProps) {
    const { data, setData, post, put, delete: destroy, processing, errors, clearErrors, reset, setDefaults } = useForm<TaskFormData>(EMPTY_TASK_FORM);

    const isEditing = task !== null;

    useEffect(() => {
        if (!open) return;

        const values = task ? toTaskFormData(task) : EMPTY_TASK_FORM;

        setDefaults(values);
        reset();
        setData(values);
        clearErrors();
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open, task?.id]);

    function submit(event: React.FormEvent) {
        event.preventDefault();

        const options = { preserveScroll: true, onSuccess: () => onOpenChange(false) };

        if (isEditing) {
            put(route('tarefas.update', task.id), options);
        } else {
            post(route('tarefas.store'), options);
        }
    }

    const toOptions = (list: Option[]) => list.map((item) => ({ value: String(item.id), label: item.name, search: item.search }));

    return (
        <Sheet open={open} onOpenChange={onOpenChange}>
            <SheetContent side="right" className="flex w-full flex-col gap-0 p-0 sm:max-w-xl">
                <SheetHeader className="border-b px-6 py-4 text-left">
                    <SheetTitle className="text-base">{isEditing ? 'Editar tarefa' : 'Nova tarefa'}</SheetTitle>
                    <SheetDescription className="sr-only">Título, situação, prioridade, prazo, responsável e cliente.</SheetDescription>
                </SheetHeader>

                <form onSubmit={submit} className="flex min-h-0 flex-1 flex-col">
                    <div className="min-h-0 flex-1 overflow-y-auto">
                        {/*
                          O título é o conteúdo, não mais um campo: entra grande e sem
                          moldura, como num editor. Os metadados ficam agrupados abaixo.
                        */}
                        <div className="space-y-1 px-6 pt-6 pb-2">
                            <Textarea
                                id="title"
                                autoFocus
                                rows={1}
                                value={data.title}
                                onChange={(e) => setData('title', e.target.value)}
                                placeholder="O que precisa ser feito?"
                                className="min-h-0 resize-none border-0 bg-transparent px-0 py-0 text-xl leading-snug font-semibold shadow-none focus-visible:ring-0"
                            />
                            {errors.title && <p className="text-destructive text-xs font-medium">{errors.title}</p>}

                            <Textarea
                                id="description"
                                rows={2}
                                value={data.description}
                                onChange={(e) => setData('description', e.target.value)}
                                placeholder="Adicionar detalhes…"
                                className="resize-none border-0 bg-transparent px-0 shadow-none focus-visible:ring-0"
                            />
                        </div>

                        <div className="space-y-1 border-t px-6 py-4">
                            <Property icon={CircleDot} label="Situação">
                                <SegmentedControl
                                    value={data.status}
                                    onChange={(value) => setData('status', value as TaskStatus)}
                                    options={taskStatusSegments}
                                    aria-label="Situação"
                                />
                            </Property>

                            <Property icon={Flag} label="Prioridade">
                                <SegmentedControl
                                    value={data.priority}
                                    onChange={(value) => setData('priority', value as TaskPriority)}
                                    options={taskPrioritySegments}
                                    aria-label="Prioridade"
                                />
                            </Property>

                            <Property icon={CalendarDays} label="Prazo" error={errors.due_date}>
                                <Input
                                    id="due_date"
                                    type="date"
                                    value={data.due_date}
                                    onChange={(e) => setData('due_date', e.target.value)}
                                    className="max-w-48"
                                />
                            </Property>

                            <Property icon={UserRound} label="Responsável" error={errors.user_id}>
                                <Combobox
                                    id="user_id"
                                    value={data.user_id}
                                    onChange={(value) => setData('user_id', value)}
                                    options={toOptions(users)}
                                    placeholder="Ninguém"
                                    searchPlaceholder="Buscar pessoa…"
                                    emptyText="Ninguém com esse nome."
                                    clearable
                                    clearLabel="Ninguém"
                                />
                            </Property>

                            <Property icon={Building2} label="Cliente" error={errors.client_id}>
                                <Combobox
                                    id="client_id"
                                    value={data.client_id}
                                    onChange={(value) => setData('client_id', value)}
                                    options={toOptions(clients)}
                                    placeholder="Nenhum"
                                    searchPlaceholder="Buscar cliente…"
                                    emptyText="Nenhum cliente com esse nome."
                                    clearable
                                    clearLabel="Nenhum"
                                />
                            </Property>
                        </div>
                    </div>

                    <div className="bg-muted/30 flex items-center justify-between gap-3 border-t px-6 py-4">
                        {isEditing ? (
                            <Button
                                type="button"
                                variant="ghost"
                                size="sm"
                                className="text-destructive hover:text-destructive"
                                onClick={() =>
                                    destroy(route('tarefas.destroy', task.id), {
                                        preserveScroll: true,
                                        onSuccess: () => onOpenChange(false),
                                    })
                                }
                            >
                                <Trash2 />
                                Excluir
                            </Button>
                        ) : (
                            <span />
                        )}

                        <div className="flex items-center gap-2">
                            <Button type="button" variant="outline" onClick={() => onOpenChange(false)}>
                                Cancelar
                            </Button>

                            <Button type="submit" loading={processing}>
                                {!processing && <Check />}
                                {isEditing ? 'Salvar' : 'Criar tarefa'}
                            </Button>
                        </div>
                    </div>
                </form>
            </SheetContent>
        </Sheet>
    );
}

/** Linha de metadado: rótulo com ícone à esquerda, controle à direita. */
function Property({ icon: Icon, label, error, children }: { icon: typeof CircleDot; label: string; error?: string; children: React.ReactNode }) {
    return (
        <div className={cn('grid grid-cols-[9rem_1fr] items-center gap-3 py-1.5', error && 'items-start')}>
            <span className="text-muted-foreground flex items-center gap-2 text-sm">
                <Icon className="size-4 shrink-0" />
                {label}
            </span>

            <div className="min-w-0">
                {children}
                {error && <p className="text-destructive mt-1 text-xs font-medium">{error}</p>}
            </div>
        </div>
    );
}
