import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import type { ActivityItem } from '@/types/dashboard';

export function RecentActivity({ items }: { items: ActivityItem[] }) {
    return (
        <Card className="flex flex-col">
            <CardHeader>
                <CardTitle>Atividades recentes</CardTitle>
                <CardDescription>O que a equipe fez por último</CardDescription>
            </CardHeader>

            <CardContent className="flex-1">
                {items.length === 0 ? (
                    <p className="text-muted-foreground text-sm">Nenhuma atividade registrada ainda.</p>
                ) : (
                    <ol className="relative space-y-5 border-l pl-5">
                        {items.map((item) => (
                            <li key={item.id} className="relative">
                                <span className="bg-primary ring-background absolute top-1.5 -left-[1.6rem] size-2 rounded-full ring-4" />
                                <p className="text-sm leading-snug">
                                    <span className="font-medium">{item.user}</span> <span className="text-muted-foreground">{item.description}</span>
                                </p>
                                <p className="text-muted-foreground mt-0.5 text-xs">{item.when}</p>
                            </li>
                        ))}
                    </ol>
                )}
            </CardContent>
        </Card>
    );
}
