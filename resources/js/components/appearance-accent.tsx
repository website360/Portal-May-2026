import { ACCENTS, usePersonalization } from '@/hooks/use-personalization';
import { cn } from '@/lib/utils';
import { Check } from 'lucide-react';

/** Seletor da cor de destaque. Aplica na hora e guarda a escolha por usuário. */
export default function AppearanceAccent({ className = '' }: { className?: string }) {
    const { accent, setAccent } = usePersonalization();

    return (
        <div className={cn('flex flex-wrap gap-2.5', className)}>
            {ACCENTS.map(({ value, label, swatch }) => {
                const active = accent === value;

                return (
                    <button
                        key={value}
                        type="button"
                        onClick={() => setAccent(value)}
                        aria-pressed={active}
                        title={label}
                        className={cn(
                            'flex size-9 items-center justify-center rounded-full transition-all duration-150',
                            'focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-primary/30 active:scale-95',
                            active ? 'ring-2 ring-offset-2 ring-offset-background' : 'hover:scale-105',
                        )}
                        style={{ backgroundColor: swatch, ...(active ? { boxShadow: `0 0 0 2px ${swatch}` } : {}) }}
                    >
                        {active && <Check className="size-4 text-white" strokeWidth={3} />}
                        <span className="sr-only">{label}</span>
                    </button>
                );
            })}
        </div>
    );
}
