import { Command, CommandEmpty, CommandGroup, CommandInput, CommandItem, CommandList } from '@/components/ui/command';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import { cn } from '@/lib/utils';
import { Check, ChevronsUpDown } from 'lucide-react';
import { useRef, useState } from 'react';

export interface MultiOption {
    value: string;
    label: string;
}

interface MultiSelectProps {
    /** Valores marcados. Vazio = sem recorte, que é o "todos". */
    value: string[];
    onChange: (value: string[]) => void;
    options: MultiOption[];
    /** Texto do gatilho quando nada está marcado. */
    allLabel: string;
    searchPlaceholder?: string;
    emptyText?: string;
    className?: string;
    'aria-label'?: string;
}

function normalize(value: string): string {
    return value
        .normalize('NFD')
        .replace(/[̀-ͯ]/g, '')
        .toLowerCase();
}

/**
 * Filtro que aceita mais de uma escolha.
 *
 * Nada marcado significa "todos" — e não "nenhum". Com filtro, essa é a leitura
 * que a pessoa espera: uma lista sem recorte mostra tudo, e ninguém precisa
 * marcar as seis situações para ver o extrato completo.
 */
export function MultiSelect({
    value,
    onChange,
    options,
    allLabel,
    searchPlaceholder = 'Buscar…',
    emptyText = 'Nada encontrado.',
    className,
    'aria-label': ariaLabel,
}: MultiSelectProps) {
    const [open, setOpen] = useState(false);
    const inputRef = useRef<HTMLInputElement>(null);

    function toggle(option: string) {
        onChange(value.includes(option) ? value.filter((v) => v !== option) : [...value, option]);
    }

    const chosen = options.filter((option) => value.includes(option.value));

    // Dois já estouram a largura do gatilho; daí o "+N".
    const label = chosen.length === 0 ? allLabel : chosen.length <= 2 ? chosen.map((o) => o.label).join(', ') : `${chosen.length} selecionados`;

    return (
        <Popover open={open} onOpenChange={setOpen} modal>
            <PopoverTrigger asChild>
                <button
                    type="button"
                    role="combobox"
                    aria-expanded={open}
                    aria-label={ariaLabel}
                    className={cn(
                        'border-input bg-background flex h-9 items-center justify-between gap-2 rounded-lg border px-3 py-1 text-left text-sm shadow-xs transition-colors',
                        'focus-visible:border-primary focus-visible:ring-primary/20 focus-visible:ring-2 focus-visible:outline-hidden',
                        className,
                    )}
                >
                    <span className={cn('truncate', chosen.length === 0 && 'text-muted-foreground/70')}>{label}</span>
                    <ChevronsUpDown className="size-4 shrink-0 opacity-50" />
                </button>
            </PopoverTrigger>

            <PopoverContent
                inline
                className="w-(--radix-popover-trigger-width) min-w-52 p-0"
                onOpenAutoFocus={() => requestAnimationFrame(() => inputRef.current?.focus())}
            >
                <Command filter={(itemValue, search) => (normalize(itemValue).includes(normalize(search)) ? 1 : 0)}>
                    <CommandInput ref={inputRef} placeholder={searchPlaceholder} />

                    <CommandList>
                        <CommandEmpty>{emptyText}</CommandEmpty>

                        <CommandGroup>
                            {/* Limpar tudo é como se volta ao "todos". */}
                            {value.length > 0 && (
                                <CommandItem value={allLabel} onSelect={() => onChange([])}>
                                    {/* Caixa vazia alinha o rótulo com os demais itens. */}
                                    <span aria-hidden className="border-input flex size-4 shrink-0 rounded border" />
                                    <span className="text-muted-foreground">{allLabel}</span>
                                </CommandItem>
                            )}

                            {options.map((option) => {
                                const checked = value.includes(option.value);

                                return (
                                    <CommandItem key={option.value} value={option.label} onSelect={() => toggle(option.value)}>
                                        {/*
                                          Caixa desenhada, não <input type="checkbox">:
                                          quem trata o clique é o próprio item da lista, e
                                          um controle interativo aninhado disputaria o
                                          evento com ele.
                                        */}
                                        <span
                                            aria-hidden
                                            className={cn(
                                                'flex size-4 shrink-0 items-center justify-center rounded border transition-colors',
                                                checked ? 'border-primary bg-primary text-primary-foreground' : 'border-input',
                                            )}
                                        >
                                            {checked && <Check className="size-3" strokeWidth={3} />}
                                        </span>
                                        <span className="truncate">{option.label}</span>
                                    </CommandItem>
                                );
                            })}
                        </CommandGroup>
                    </CommandList>
                </Command>
            </PopoverContent>
        </Popover>
    );
}
