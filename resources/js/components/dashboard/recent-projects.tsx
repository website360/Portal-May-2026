import { Badge } from '@/components/ui/badge';
import { Card, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { projectStatus, toneFor } from '@/config/domain';
import { formatCurrency } from '@/lib/format';
import { cn } from '@/lib/utils';
import type { RecentProject } from '@/types/dashboard';

export function RecentProjects({ projects }: { projects: RecentProject[] }) {
    return (
        <Card>
            <CardHeader>
                <CardTitle>Projetos recentes</CardTitle>
                <CardDescription>Os últimos projetos abertos na agência</CardDescription>
            </CardHeader>

            {projects.length === 0 ? (
                <p className="text-muted-foreground px-6 pb-6 text-sm">Nenhum projeto cadastrado ainda.</p>
            ) : (
                <div className="overflow-x-auto">
                    <table className="w-full text-sm">
                        <thead>
                            <tr className="bg-muted/40 text-muted-foreground border-y text-left text-xs font-medium tracking-wide uppercase">
                                <th className="py-2.5 pr-4 pl-6 font-medium">Cliente</th>
                                <th className="px-4 py-2.5 font-medium">Projeto</th>
                                <th className="px-4 py-2.5 font-medium">Prazo</th>
                                <th className="px-4 py-2.5 text-right font-medium">Valor</th>
                                <th className="py-2.5 pr-6 pl-4 font-medium">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            {projects.map((project) => {
                                const tone = toneFor(projectStatus, project.status);

                                return (
                                    <tr key={project.id} className="hover:bg-muted/40 border-b transition-colors last:border-b-0">
                                        <td className={cn('border-l-2 py-3 pr-4 pl-6', tone.border)}>
                                            <span className="font-medium">{project.client}</span>
                                        </td>
                                        <td className="text-muted-foreground px-4 py-3">{project.name}</td>
                                        <td className="tabular text-muted-foreground px-4 py-3">{project.dueDate ?? '—'}</td>
                                        <td className="tabular px-4 py-3 text-right">{formatCurrency(project.budget)}</td>
                                        <td className="py-3 pr-6 pl-4">
                                            <Badge variant={tone.variant}>{tone.label}</Badge>
                                        </td>
                                    </tr>
                                );
                            })}
                        </tbody>
                    </table>
                </div>
            )}
        </Card>
    );
}
