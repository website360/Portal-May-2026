import { Badge } from '@/components/ui/badge';
import { clientStatus, toneFor } from '@/config/domain';
import { cn } from '@/lib/utils';
import type { Client } from '@/types/clients';

/**
 * Blocos de leitura compartilhados pela pagina do cliente e pela visualizacao
 * rapida — os dois mostram os mesmos campos, so mudam de moldura.
 */

export function ClientStatusBadge({ status }: { status: string }) {
    const tone = toneFor(clientStatus, status);

    return <Badge variant={tone.variant}>{tone.label}</Badge>;
}

export function ClientTypeBadge({ type }: { type: Client['type'] }) {
    return <Badge variant="outline">{type === 'person' ? 'Pessoa física' : 'Pessoa jurídica'}</Badge>;
}

export function DetailGroup({ title, children, className }: { title: string; children: React.ReactNode; className?: string }) {
    return (
        <div className={cn('space-y-3', className)}>
            <h3 className="text-muted-foreground text-xs font-semibold tracking-wide uppercase">{title}</h3>
            <dl className="grid gap-3 sm:grid-cols-2">{children}</dl>
        </div>
    );
}

export function DetailItem({ label, value, wide }: { label: string; value: React.ReactNode; wide?: boolean }) {
    const empty = value === null || value === undefined || value === '';

    return (
        <div className={cn('min-w-0', wide && 'sm:col-span-2')}>
            <dt className="text-muted-foreground text-xs">{label}</dt>
            <dd className={cn('mt-0.5 text-sm break-words', empty && 'text-muted-foreground')}>{empty ? '—' : value}</dd>
        </div>
    );
}

/** Endereço numa linha só, pulando as partes que o cliente não preencheu. */
export function formatAddress(client: Client): string | null {
    const line = [client.street, client.number].filter(Boolean).join(', ');
    const parts = [line, client.complement, client.district, [client.city, client.state].filter(Boolean).join('/')].filter(Boolean);

    return parts.length > 0 ? parts.join(' · ') : null;
}
