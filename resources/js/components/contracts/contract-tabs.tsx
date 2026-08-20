import { cn } from '@/lib/utils';
import { Link } from '@inertiajs/react';

/**
 * Abas da seção Contratos. Cada aba é uma rota — a lista dos ativos e a geração
 * do documento vivem sob o mesmo título, trocadas aqui em vez de no menu lateral.
 */
const tabs = [
    { title: 'Ativos', href: '/contratos' },
    { title: 'Criar contrato', href: '/contratos/gerar' },
];

export function ContractTabs({ current }: { current: string }) {
    return (
        <div className="border-b">
            <nav className="-mb-px flex gap-1">
                {tabs.map((tab) => {
                    const active = current === tab.href;

                    return (
                        <Link
                            key={tab.href}
                            href={tab.href}
                            prefetch
                            className={cn(
                                'border-b-2 px-4 py-2.5 text-sm font-medium transition-colors',
                                'focus-visible:ring-primary/20 rounded-t-md focus-visible:ring-2 focus-visible:outline-hidden',
                                active
                                    ? 'border-primary text-foreground'
                                    : 'text-muted-foreground hover:text-foreground hover:border-border border-transparent',
                            )}
                        >
                            {tab.title}
                        </Link>
                    );
                })}
            </nav>
        </div>
    );
}
