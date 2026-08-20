import { cn } from '@/lib/utils';
import { Link } from '@inertiajs/react';
import { KanbanSquare, List } from 'lucide-react';

/**
 * Alterna entre a lista e o quadro. Um controle segmentado: a visão atual fica
 * acesa, a outra é um link.
 */
export function TicketsViewToggle({ current }: { current: 'list' | 'board' }) {
    return (
        <div className="bg-muted inline-flex items-center rounded-lg p-0.5">
            <Item href="/tickets" icon={List} label="Lista" active={current === 'list'} />
            <Item href="/tickets/quadro" icon={KanbanSquare} label="Quadro" active={current === 'board'} />
        </div>
    );
}

function Item({ href, icon: Icon, label, active }: { href: string; icon: typeof List; label: string; active: boolean }) {
    return (
        <Link
            href={href}
            className={cn(
                'inline-flex items-center gap-1.5 rounded-md px-2.5 py-1.5 text-sm font-medium transition-colors',
                active ? 'bg-background text-foreground shadow-sm' : 'text-muted-foreground hover:text-foreground',
            )}
        >
            <Icon className="size-4" />
            {label}
        </Link>
    );
}
