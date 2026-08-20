import { PriceReviewNoticeButton, type PriceReviewContract } from '@/components/contracts/price-review-notice-button';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { formatCurrency } from '@/lib/format';
import { Link } from '@inertiajs/react';
import { ArrowRight, TrendingUp } from 'lucide-react';

export interface PriceReview extends PriceReviewContract {
    service: string;
    client: string;
    review_days: number | null;
}

/** Quando o reajuste chegou: vencido, hoje, ou em N dias. */
function whenLabel(days: number | null, dateLabel: string | null): string {
    if (dateLabel === null) return '';
    if (days === null) return `reajuste em ${dateLabel}`;
    if (days < 0) return `reajuste vencido (${dateLabel})`;
    if (days === 0) return `reajuste hoje (${dateLabel})`;
    return `reajuste em ${days} ${days === 1 ? 'dia' : 'dias'} (${dateLabel})`;
}

/**
 * Contratos na hora do reajuste bianual, com o botão de avisar o cliente ali
 * mesmo. Some quando não há nenhum — cartão vazio só ocupa atenção.
 */
export function PriceReviews({ reviews }: { reviews: PriceReview[] }) {
    if (reviews.length === 0) {
        return null;
    }

    return (
        <Card>
            <CardHeader className="flex flex-row items-center justify-between gap-3">
                <CardTitle className="flex items-center gap-2 text-base">
                    <TrendingUp className="text-warning size-4" />
                    A reajustar
                </CardTitle>

                <Button variant="ghost" size="sm" asChild>
                    <Link href={route('contratos.index')}>
                        Ver todos
                        <ArrowRight />
                    </Link>
                </Button>
            </CardHeader>

            <CardContent className="p-0">
                <ul className="border-t">
                    {reviews.map((item) => (
                        <li key={item.id} className="flex flex-wrap items-center gap-x-3 gap-y-2 border-b px-6 py-3 last:border-b-0">
                            <div className="min-w-40 flex-1">
                                <p className="truncate text-sm font-medium">{item.client}</p>
                                <p className="text-muted-foreground truncate text-xs">
                                    {item.service} · nº {item.number} · {whenLabel(item.review_days, item.price_review_label)}
                                </p>
                            </div>

                            <span className="tabular text-sm">{item.value !== null ? formatCurrency(item.value) : '—'}</span>

                            <PriceReviewNoticeButton contract={item} />
                        </li>
                    ))}
                </ul>
            </CardContent>
        </Card>
    );
}
