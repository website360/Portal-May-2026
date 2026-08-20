import { ClientAvatar } from '@/components/clients/client-avatar';
import { TicketStatusBadge } from '@/components/tickets/ticket-badges';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Combobox } from '@/components/ui/combobox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
import { type BreadcrumbItem } from '@/types';
import type { ClientOption } from '@/types/domains';
import {
    ticketChannelLabels,
    ticketPriorityLabels,
    ticketPriorityOrder,
    ticketStatusLabels,
    ticketStatusOrder,
    type AgentOption,
    type TicketDetail,
    type TicketMessage,
} from '@/types/tickets';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { ArrowLeft, Calendar, Lock, Paperclip, Radio, Send, User, X } from 'lucide-react';
import { useRef } from 'react';

interface TicketShowProps {
    ticket: TicketDetail;
    clients: ClientOption[];
    agents: AgentOption[];
    categories: string[];
}

export default function TicketShow({ ticket, clients, agents }: TicketShowProps) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Tickets', href: '/tickets' },
        { title: ticket.number, href: `/tickets/${ticket.id}` },
    ];

    /** Edição inline das propriedades — cada campo salva sozinho. */
    function patch(payload: Record<string, string | null>) {
        router.put(route('tickets.update', ticket.id), payload, { preserveScroll: true });
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`${ticket.number} · ${ticket.subject}`} />

            <div className="animate-fade-in flex min-w-0 flex-1 flex-col gap-6 p-6">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <div className="min-w-0 space-y-1">
                        <Link href="/tickets" className="text-muted-foreground hover:text-foreground inline-flex items-center gap-1.5 text-sm">
                            <ArrowLeft className="size-4" />
                            Tickets
                        </Link>
                        <div className="flex items-center gap-2">
                            <span className="text-muted-foreground tabular text-sm">{ticket.number}</span>
                            <TicketStatusBadge status={ticket.status} />
                        </div>
                        <h1 className="text-2xl font-bold tracking-tight">{ticket.subject}</h1>
                    </div>
                </div>

                <div className="grid gap-6 lg:grid-cols-[1fr_20rem]">
                    <div className="flex min-w-0 flex-col gap-4">
                        <div className="flex flex-col gap-3">
                            {ticket.messages.map((message) => (
                                <MessageBubble key={message.id} message={message} />
                            ))}
                        </div>

                        <ReplyComposer ticketId={ticket.id} />
                    </div>

                    <aside className="flex flex-col gap-4">
                        <Card className="flex flex-col gap-4 p-4">
                            <PropRow label="Situação">
                                <Select value={ticket.status} onValueChange={(v) => patch({ status: v })}>
                                    <SelectTrigger className="h-9">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {ticketStatusOrder.map((s) => (
                                            <SelectItem key={s} value={s}>
                                                {ticketStatusLabels[s]}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </PropRow>

                            <PropRow label="Prioridade">
                                <Select value={ticket.priority} onValueChange={(v) => patch({ priority: v })}>
                                    <SelectTrigger className="h-9">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {ticketPriorityOrder.map((p) => (
                                            <SelectItem key={p} value={p}>
                                                {ticketPriorityLabels[p]}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </PropRow>

                            <PropRow label="Responsável">
                                <Select
                                    value={ticket.assignee_id ? String(ticket.assignee_id) : 'unassigned'}
                                    onValueChange={(v) => patch({ assignee_id: v === 'unassigned' ? '' : v })}
                                >
                                    <SelectTrigger className="h-9">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="unassigned">Sem responsável</SelectItem>
                                        {agents.map((a) => (
                                            <SelectItem key={a.value} value={a.value}>
                                                {a.label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </PropRow>

                            <PropRow label="Cliente">
                                <Combobox
                                    value={ticket.client_id ? String(ticket.client_id) : ''}
                                    onChange={(v) => patch({ client_id: v })}
                                    options={clients.map((c) => ({ value: String(c.id), label: c.name, search: c.search }))}
                                    placeholder="Sem cliente"
                                    searchPlaceholder="Buscar cliente…"
                                    emptyText="Nenhum cliente."
                                    clearable
                                    clearLabel="Sem cliente"
                                />
                            </PropRow>

                            <PropRow label="Categoria">
                                <CategoryField value={ticket.category ?? ''} onSave={(v) => patch({ category: v })} />
                            </PropRow>
                        </Card>

                        <Card className="text-muted-foreground flex flex-col gap-2 p-4 text-sm">
                            <Meta icon={User} label="Aberto por" value={ticket.opener?.name ?? '—'} />
                            <Meta icon={Radio} label="Canal" value={ticketChannelLabels[ticket.channel] ?? ticket.channel} />
                            <Meta icon={Calendar} label="Criado em" value={ticket.created_at_label} />
                        </Card>
                    </aside>
                </div>
            </div>
        </AppLayout>
    );
}

function MessageBubble({ message }: { message: TicketMessage }) {
    const author = message.user?.name ?? 'Sistema';

    return (
        <Card className={cn('flex flex-col gap-2 p-4', message.internal && 'border-warning/40 bg-warning/5')}>
            <div className="flex items-center gap-2.5">
                <ClientAvatar name={author} className="size-8" />
                <div className="min-w-0 flex-1">
                    <div className="flex items-center gap-2">
                        <span className="text-sm font-medium">{author}</span>
                        {message.internal && (
                            <span className="bg-warning/15 text-warning inline-flex items-center gap-1 rounded px-1.5 py-0.5 text-xs font-medium">
                                <Lock className="size-3" />
                                Nota interna
                            </span>
                        )}
                    </div>
                    <span className="text-muted-foreground text-xs" title={message.created_at}>
                        {message.created_label}
                    </span>
                </div>
            </div>

            <p className="text-sm whitespace-pre-wrap">{message.body}</p>

            {message.attachments.length > 0 && (
                <div className="flex flex-wrap gap-2 pt-1">
                    {message.attachments.map((file) => (
                        <a
                            key={file.id}
                            href={file.url}
                            target="_blank"
                            rel="noreferrer"
                            className="border-border bg-muted/40 hover:bg-muted inline-flex max-w-56 items-center gap-2 rounded-md border px-2.5 py-1.5 text-xs transition-colors"
                        >
                            <Paperclip className="size-3.5 shrink-0" />
                            <span className="truncate">{file.name}</span>
                            <span className="text-muted-foreground shrink-0">{formatSize(file.size)}</span>
                        </a>
                    ))}
                </div>
            )}
        </Card>
    );
}

function ReplyComposer({ ticketId }: { ticketId: number }) {
    const fileInput = useRef<HTMLInputElement>(null);
    const { data, setData, post, processing, errors, reset } = useForm<{ body: string; internal: boolean; attachments: File[] }>({
        body: '',
        internal: false,
        attachments: [],
    });

    function submit(event: React.FormEvent) {
        event.preventDefault();
        post(route('tickets.reply', ticketId), {
            forceFormData: data.attachments.length > 0,
            preserveScroll: true,
            onSuccess: () => {
                reset();
                if (fileInput.current) fileInput.current.value = '';
            },
        });
    }

    function addFiles(list: FileList | null) {
        if (!list) return;
        setData('attachments', [...data.attachments, ...Array.from(list)].slice(0, 5));
    }

    function removeFile(index: number) {
        setData(
            'attachments',
            data.attachments.filter((_, i) => i !== index),
        );
    }

    return (
        <Card className={cn('flex flex-col gap-3 p-4', data.internal && 'border-warning/40 bg-warning/5')}>
            <form onSubmit={submit} className="flex flex-col gap-3">
                <Textarea
                    rows={4}
                    value={data.body}
                    onChange={(e) => setData('body', e.target.value)}
                    placeholder={data.internal ? 'Escreva uma nota interna (só a equipe vê)…' : 'Escreva uma resposta…'}
                />
                {errors.body && <p className="text-destructive text-xs font-medium">{errors.body}</p>}

                {data.attachments.length > 0 && (
                    <div className="flex flex-wrap gap-2">
                        {data.attachments.map((file, index) => (
                            <span
                                key={index}
                                className="border-border bg-muted/40 inline-flex max-w-56 items-center gap-2 rounded-md border px-2.5 py-1.5 text-xs"
                            >
                                <Paperclip className="size-3.5 shrink-0" />
                                <span className="truncate">{file.name}</span>
                                <button type="button" onClick={() => removeFile(index)} className="text-muted-foreground hover:text-destructive">
                                    <X className="size-3.5" />
                                </button>
                            </span>
                        ))}
                    </div>
                )}

                <div className="flex flex-wrap items-center justify-between gap-2">
                    <div className="flex items-center gap-1">
                        <input ref={fileInput} type="file" multiple className="hidden" onChange={(e) => addFiles(e.target.files)} />
                        <Button type="button" variant="ghost" size="sm" onClick={() => fileInput.current?.click()}>
                            <Paperclip />
                            Anexar
                        </Button>
                        <Button
                            type="button"
                            variant={data.internal ? 'secondary' : 'ghost'}
                            size="sm"
                            aria-pressed={data.internal}
                            onClick={() => setData('internal', !data.internal)}
                            className={cn(data.internal && 'text-warning')}
                        >
                            <Lock />
                            Nota interna
                        </Button>
                    </div>

                    <Button type="submit" loading={processing} disabled={data.body.trim() === ''}>
                        {!processing && <Send />}
                        {data.internal ? 'Salvar nota' : 'Responder'}
                    </Button>
                </div>
            </form>
        </Card>
    );
}

/** Categoria salva no blur, só quando muda de fato. */
function CategoryField({ value, onSave }: { value: string; onSave: (value: string) => void }) {
    const original = useRef(value);

    return (
        <Input
            className="h-9"
            defaultValue={value}
            placeholder="Sem categoria"
            onBlur={(e) => {
                const next = e.target.value.trim();
                if (next !== original.current) {
                    original.current = next;
                    onSave(next);
                }
            }}
        />
    );
}

function PropRow({ label, children }: { label: string; children: React.ReactNode }) {
    return (
        <div className="grid gap-1.5">
            <Label className="text-muted-foreground text-xs font-medium tracking-wide uppercase">{label}</Label>
            {children}
        </div>
    );
}

function Meta({ icon: Icon, label, value }: { icon: typeof User; label: string; value: string }) {
    return (
        <div className="flex items-center gap-2">
            <Icon className="size-4 shrink-0" />
            <span>{label}</span>
            <span className="text-foreground ml-auto truncate font-medium">{value}</span>
        </div>
    );
}

function formatSize(bytes: number): string {
    if (bytes < 1024) return `${bytes} B`;
    if (bytes < 1024 * 1024) return `${Math.round(bytes / 1024)} KB`;
    return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
}
