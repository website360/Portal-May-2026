import { Card, CardContent } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/react';
import { ArrowRight, Blocks, Receipt, type LucideIcon } from 'lucide-react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Ferramentas', href: '/ferramentas' },
];

/** O servidor manda um nome de ícone; a tela decide o desenho. */
const icones: Record<string, LucideIcon> = {
    receipt: Receipt,
};

interface Tool {
    slug: string;
    name: string;
    description: string;
    icon: string;
}

export default function Ferramentas({ tools }: { tools: Tool[] }) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Ferramentas" />

            <div className="animate-fade-in flex min-w-0 flex-1 flex-col gap-6 p-6">
                <div className="space-y-1">
                    <h1 className="text-2xl font-bold tracking-tight">Ferramentas</h1>
                    <p className="text-muted-foreground text-sm">Contas rápidas do dia a dia. Entra, resolve, sai — sem cadastrar nada.</p>
                </div>

                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                    {tools.map((tool) => {
                        const Icone = icones[tool.icon] ?? Blocks;

                        return (
                            <Link key={tool.slug} href={`/ferramentas/${tool.slug}`} className="group">
                                <Card className="hover:border-primary/40 h-full transition-all hover:shadow-md">
                                    <CardContent className="flex h-full flex-col gap-3 p-5">
                                        <span className="bg-accent text-accent-foreground flex size-10 shrink-0 items-center justify-center rounded-lg">
                                            <Icone className="size-5" />
                                        </span>

                                        <div className="space-y-1">
                                            <p className="font-medium">{tool.name}</p>
                                            <p className="text-muted-foreground text-sm">{tool.description}</p>
                                        </div>

                                        <span className="text-primary mt-auto flex items-center gap-1 pt-2 text-sm font-medium">
                                            Abrir
                                            <ArrowRight className="size-4 transition-transform group-hover:translate-x-0.5" />
                                        </span>
                                    </CardContent>
                                </Card>
                            </Link>
                        );
                    })}
                </div>
            </div>
        </AppLayout>
    );
}
