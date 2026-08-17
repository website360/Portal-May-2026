import { colorOf, paletteColors } from '@/config/domain';
import { cn } from '@/lib/utils';

/** Paleta fechada: cor escolhida aqui continua legível no claro e no escuro. */
export function ColorPicker({ value, onChange, colors }: { value: string; onChange: (color: string) => void; colors: string[] }) {
    return (
        <div className="flex items-center gap-1.5" role="radiogroup" aria-label="Cor">
            {colors.map((color) => {
                const tone = colorOf(color);
                const active = color === value;

                return (
                    <button
                        key={color}
                        type="button"
                        role="radio"
                        aria-checked={active}
                        aria-label={paletteColors[color]?.label ?? color}
                        title={paletteColors[color]?.label ?? color}
                        onClick={() => onChange(color)}
                        className={cn(
                            'size-6 cursor-pointer rounded-full transition-all duration-150',
                            'focus-visible:ring-primary/20 focus-visible:ring-2 focus-visible:outline-hidden active:scale-90',
                            tone.dot,
                            active ? 'ring-foreground/30 ring-offset-background scale-110 ring-2 ring-offset-2' : 'opacity-60 hover:opacity-100',
                        )}
                    />
                );
            })}
        </div>
    );
}
