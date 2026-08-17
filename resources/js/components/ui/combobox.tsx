import { Command, CommandEmpty, CommandGroup, CommandInput, CommandItem, CommandList } from '@/components/ui/command';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import { cn } from '@/lib/utils';
import { Check, ChevronsUpDown, X } from 'lucide-react';
import { useRef, useState } from 'react';

export interface ComboboxOption {
    value: string;
    label: string;
    /** Texto secundário à direita, útil para desempatar homônimos. */
    hint?: string;
    /**
     * Termos que encontram a opção sem aparecer nela.
     *
     * O cliente é rotulado pela marca, mas quem digita "Adriana" ou o CNPJ
     * também precisa achar "Inove-se".
     */
    search?: string;
}

/**
 * Remove acento e caixa para comparar.
 *
 * Sem isso, procurar "agencia" não acha "Agência" e "comercio" não acha
 * "Comércio" — e digitar acento no meio de uma busca rápida é justamente o que
 * ninguém faz.
 */
function normalize(value: string): string {
    return value
        .normalize('NFD')
        .replace(/[̀-ͯ]/g, '')
        .toLowerCase();
}

interface ComboboxProps {
    value: string;
    onChange: (value: string) => void;
    options: ComboboxOption[];
    id?: string;
    /** Texto do gatilho quando nada está escolhido. */
    placeholder?: string;
    searchPlaceholder?: string;
    emptyText?: string;
    /** Permite voltar ao vazio; o rótulo aparece como primeira opção. */
    clearable?: boolean;
    clearLabel?: string;
    disabled?: boolean;
    className?: string;
}

/**
 * Select com busca. Toda lista que pode passar de uma dúzia de itens usa este
 * componente — rolar atrás de um cliente entre cinquenta não é aceitável.
 */
export function Combobox({
    value,
    onChange,
    options,
    id,
    placeholder = 'Selecione',
    searchPlaceholder = 'Buscar…',
    emptyText = 'Nada encontrado.',
    clearable = false,
    clearLabel = 'Nenhum',
    disabled,
    className,
}: ComboboxProps) {
    const [open, setOpen] = useState(false);
    const inputRef = useRef<HTMLInputElement>(null);

    const selected = options.find((option) => option.value === value);

    function choose(next: string) {
        onChange(next);
        setOpen(false);
    }

    return (
        /*
         * `modal` é o que faz este componente funcionar dentro das gavetas.
         *
         * Sheet é um Dialog modal do Radix: enquanto aberto, ele zera o
         * pointer-events do body. A lista do Popover vai para um portal fora do
         * Dialog e herda esse zero — o clique atravessa a opção e acerta o
         * formulário atrás dela. Com `modal`, o Popover cria a própria camada,
         * devolve o pointer-events ao conteúdo e para o clique antes que o
         * Dialog o interprete como "clicou fora, feche tudo".
         */
        <Popover open={open} onOpenChange={setOpen} modal>
            <PopoverTrigger asChild>
                <button
                    type="button"
                    id={id}
                    role="combobox"
                    aria-expanded={open}
                    disabled={disabled}
                    className={cn(
                        'flex h-9 w-full items-center justify-between gap-2 rounded-lg border border-input bg-background px-3 py-1 text-left text-sm shadow-xs transition-colors',
                        'focus-visible:border-primary focus-visible:ring-2 focus-visible:ring-primary/20 focus-visible:outline-hidden',
                        'disabled:cursor-not-allowed disabled:opacity-50',
                        className,
                    )}
                >
                    <span className={cn('truncate', !selected && 'text-muted-foreground/70')}>{selected?.label ?? placeholder}</span>
                    <ChevronsUpDown className="size-4 shrink-0 opacity-50" />
                </button>
            </PopoverTrigger>

            <PopoverContent
                inline
                className="w-(--radix-popover-trigger-width) p-0"
                /*
                 * Manda o foco para o campo de busca ao abrir.
                 *
                 * Com o Popover modal, o Radix devolvia o foco ao gatilho e o
                 * que a pessoa digitava não chegava a lugar nenhum — o select
                 * abria, a lista aparecia, e digitar não filtrava nada.
                 */
                /*
                 * Sem preventDefault: o Popover modal já leva o foco para o
                 * primeiro elemento focável do conteúdo, que é o campo de busca.
                 * Bloquear esse comportamento era o que deixava o foco no botão
                 * do gatilho — a lista abria e digitar não fazia nada.
                 *
                 * O focus() extra cobre o caso da gaveta, onde o Dialog disputa
                 * o foco; roda depois, no quadro seguinte.
                 */
                onOpenAutoFocus={() => requestAnimationFrame(() => inputRef.current?.focus())}
            >
                <Command
                    /*
                     * Filtro próprio: o padrão do cmdk é sensível a acento, e
                     * "sao paulo" não acharia "São Paulo". Aqui basta conter o
                     * que foi digitado, já normalizado dos dois lados.
                     */
                    filter={(value, search) => (normalize(value).includes(normalize(search)) ? 1 : 0)}
                >
                    <CommandInput ref={inputRef} placeholder={searchPlaceholder} />

                    <CommandList>
                        <CommandEmpty>{emptyText}</CommandEmpty>

                        <CommandGroup>
                            {clearable && (
                                <CommandItem value={clearLabel} onSelect={() => choose('')}>
                                    <X className="size-4 shrink-0 opacity-60" />
                                    <span className="text-muted-foreground">{clearLabel}</span>
                                </CommandItem>
                            )}

                            {options.map((option) => (
                                <CommandItem
                                    key={option.value}
                                    // Tudo que encontra a opção entra aqui; só o
                                    // label e o hint aparecem na linha.
                                    value={[option.label, option.hint, option.search].filter(Boolean).join(' ')}
                                    onSelect={() => choose(option.value)}
                                >
                                    <Check className={cn('size-4 shrink-0', option.value !== value && 'opacity-0')} />
                                    <span className="truncate">{option.label}</span>
                                    {option.hint && <span className="ml-auto shrink-0 text-xs text-muted-foreground">{option.hint}</span>}
                                </CommandItem>
                            ))}
                        </CommandGroup>
                    </CommandList>
                </Command>
            </PopoverContent>
        </Popover>
    );
}
