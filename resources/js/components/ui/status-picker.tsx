import { badgeVariants, type BadgeProps } from '@/components/ui/badge';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuLabel, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { cn } from '@/lib/utils';
import { router } from '@inertiajs/react';
import { Check, ChevronDown, Loader2, type LucideIcon } from 'lucide-react';
import { useState } from 'react';

export interface StatusOption {
    value: string;
    label: string;
    variant: NonNullable<BadgeProps['variant']>;
    icon?: LucideIcon;
    hint?: string;
    /**
     * Situações derivadas (como "vencida", que sai do vencimento) precisam
     * aparecer no badge, mas não podem ser escolhidas à mão.
     */
    selectable?: boolean;
}

interface StatusPickerProps {
    value: string;
    options: StatusOption[];
    /** Rota PATCH que recebe `{ [field]: novoValor }`. */
    url: string;
    field: string;
    /** Aparece no rótulo do menu e no aria-label. */
    label?: string;
    /** Sem permissão de troca: vira um badge comum. */
    readOnly?: boolean;
    /**
     * Chance de tratar a escolha por conta própria.
     *
     * Devolvendo `true`, o seletor não envia nada — quem chamou assume. Serve
     * para situações que precisam de mais informação antes de valer, como uma
     * baixa que pode ter acontecido em outro dia.
     */
    onIntercept?: (value: string) => boolean;
    className?: string;
}

/**
 * Badge que também é seletor: clicar abre um menu curto e a escolha vai direto
 * para o servidor, sem sair da listagem.
 *
 * A troca é um PATCH em rota própria por recurso — cada uma valida só o campo
 * que aceita mudar. Um endpoint genérico de "atualize esta coluna" abriria a
 * porta para escrever em qualquer campo.
 */
export function StatusPicker({ value, options, url, field, label = 'situação', readOnly, onIntercept, className }: StatusPickerProps) {
    const [saving, setSaving] = useState(false);

    const current = options.find((option) => option.value === value) ?? options[0];
    const Icon = current?.icon;

    if (!current) {
        return null;
    }

    function choose(next: string) {
        if (next === value) return;

        if (onIntercept?.(next)) {
            return;
        }

        setSaving(true);

        router.patch(
            url,
            { [field]: next },
            {
                preserveScroll: true,
                preserveState: true,
                onFinish: () => setSaving(false),
            },
        );
    }

    if (readOnly) {
        return (
            <span className={cn(badgeVariants({ variant: current.variant }), className)}>
                {Icon && <Icon />}
                {current.label}
            </span>
        );
    }

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild disabled={saving}>
                <button
                    type="button"
                    aria-label={`Alterar ${label}: ${current.label}`}
                    className={cn(
                        badgeVariants({ variant: current.variant }),
                        'cursor-pointer pr-1 transition-all duration-150',
                        'hover:shadow-sm focus-visible:ring-2 focus-visible:ring-primary/20 focus-visible:outline-hidden',
                        'active:scale-[0.97] disabled:cursor-wait',
                        className,
                    )}
                >
                    {Icon && !saving && <Icon />}
                    {/*
                      Largura fixa no rótulo, não no selo: assim "Paga" e
                      "Vencida" ocupam o mesmo espaço e a coluna fica alinhada,
                      sem a seta dançando de linha para linha.
                    */}
                    <span className="min-w-14 text-left">{current.label}</span>
                    {saving ? <Loader2 className="animate-spin" /> : <ChevronDown className="opacity-60" />}
                </button>
            </DropdownMenuTrigger>

            <DropdownMenuContent align="start" className="w-56">
                <DropdownMenuLabel className="text-muted-foreground text-xs font-normal">Alterar {label}</DropdownMenuLabel>

                {options
                    .filter((option) => option.selectable !== false)
                    .map((option) => {
                        const OptionIcon = option.icon;
                        const active = option.value === value;

                        return (
                            <DropdownMenuItem key={option.value} onSelect={() => choose(option.value)} className="gap-2">
                                <Check className={cn('size-4 shrink-0', !active && 'opacity-0')} />

                                <span className={cn(badgeVariants({ variant: option.variant }), 'shrink-0')}>
                                    {OptionIcon && <OptionIcon />}
                                    {option.label}
                                </span>

                                {option.hint && <span className="text-muted-foreground ml-auto truncate text-xs">{option.hint}</span>}
                            </DropdownMenuItem>
                        );
                    })}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
