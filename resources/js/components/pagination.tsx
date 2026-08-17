import { Button } from '@/components/ui/button';
import type { Paginated } from '@/types';
import { router } from '@inertiajs/react';
import { ChevronLeft, ChevronRight } from 'lucide-react';

/**
 * Pager compacto a partir dos `links` do paginador do Laravel. O primeiro e o
 * ultimo link sao "Anterior"/"Proxima"; o miolo sao as paginas.
 */
export function Pagination<T>({ page }: { page: Paginated<T> }) {
    const previous = page.links[0];
    const next = page.links[page.links.length - 1];
    const numbers = page.links.slice(1, -1);
    const hasPages = page.last_page > 1;

    const go = (url: string | null) => url && router.get(url, {}, { preserveState: true, preserveScroll: true });

    return (
        <div className="flex flex-wrap items-center justify-between gap-3 border-t px-6 py-4">
            {/* O resumo aparece mesmo com uma pagina so: e a contagem do que esta em tela. */}
            <p className="text-muted-foreground text-xs">
                Mostrando <span className="tabular">{page.from ?? 0}</span>–<span className="tabular">{page.to ?? 0}</span> de{' '}
                <span className="tabular">{page.total}</span>
            </p>

            {hasPages && (
                <div className="flex items-center gap-1">
                    <Button variant="outline" size="icon-sm" disabled={!previous.url} onClick={() => go(previous.url)} aria-label="Página anterior">
                        <ChevronLeft />
                    </Button>

                    {numbers.map((link, index) => (
                        <Button
                            key={`${link.label}-${index}`}
                            variant={link.active ? 'default' : 'ghost'}
                            size="icon-sm"
                            disabled={!link.url}
                            onClick={() => go(link.url)}
                            className="tabular text-xs"
                        >
                            {link.label}
                        </Button>
                    ))}

                    <Button variant="outline" size="icon-sm" disabled={!next.url} onClick={() => go(next.url)} aria-label="Próxima página">
                        <ChevronRight />
                    </Button>
                </div>
            )}
        </div>
    );
}
