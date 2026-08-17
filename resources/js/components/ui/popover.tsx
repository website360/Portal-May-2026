import * as PopoverPrimitive from '@radix-ui/react-popover';
import * as React from 'react';

import { cn } from '@/lib/utils';

const Popover = PopoverPrimitive.Root;

const PopoverTrigger = PopoverPrimitive.Trigger;

const PopoverAnchor = PopoverPrimitive.Anchor;

interface PopoverContentProps extends React.ComponentPropsWithoutRef<typeof PopoverPrimitive.Content> {
    /**
     * Renderiza sem portal, dentro do próprio DOM do gatilho.
     *
     * Necessário quando o popover vive dentro de uma gaveta: o Dialog prende o
     * foco no que está dentro dele, e conteúdo portalado para fora vira "área
     * externa" — o campo de busca abre mas nunca recebe o que se digita.
     */
    inline?: boolean;
}

const PopoverContent = React.forwardRef<React.ElementRef<typeof PopoverPrimitive.Content>, PopoverContentProps>(
    ({ className, align = 'start', sideOffset = 4, inline = false, ...props }, ref) => {
        const Wrapper = inline ? React.Fragment : PopoverPrimitive.Portal;

        return (
            <Wrapper>
                <PopoverPrimitive.Content
                    ref={ref}
                    align={align}
                    sideOffset={sideOffset}
                    className={cn(
                        'z-50 rounded-lg border bg-popover p-1 text-popover-foreground shadow-lg outline-hidden',
                        'data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:fade-in-0',
                        'data-[state=closed]:zoom-out-95 data-[state=open]:zoom-in-95',
                        'data-[side=bottom]:slide-in-from-top-2 data-[side=left]:slide-in-from-right-2 data-[side=right]:slide-in-from-left-2 data-[side=top]:slide-in-from-bottom-2',
                        className,
                    )}
                    {...props}
                />
            </Wrapper>
        );
    },
);
PopoverContent.displayName = PopoverPrimitive.Content.displayName;

export { Popover, PopoverAnchor, PopoverContent, PopoverTrigger };
