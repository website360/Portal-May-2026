import { Button } from '@/components/ui/button';
import { Camera, Trash2, Upload } from 'lucide-react';
import { useEffect, useRef, useState, type ReactNode } from 'react';

interface PhotoPickerProps {
    /** Arquivo recem-escolhido, ainda nao enviado. */
    file: File | null;
    /** Foto ja gravada no servidor. */
    existingUrl?: string | null;
    /** A gravada esta marcada para remocao ao salvar. */
    removed?: boolean;
    onSelect: (file: File | null) => void;
    onRemove: () => void;
    /**
     * Quem chama decide como o avatar aparece — cliente e usuario derivam as
     * iniciais de formas diferentes, e essa regra nao pertence ao seletor.
     */
    renderAvatar: (photoUrl: string | null) => ReactNode;
    hint?: string;
}

export function PhotoPicker({
    file,
    existingUrl,
    removed,
    onSelect,
    onRemove,
    renderAvatar,
    hint = 'JPG, PNG ou WEBP, até 2 MB. Sem foto, mostramos as iniciais.',
}: PhotoPickerProps) {
    const inputRef = useRef<HTMLInputElement>(null);
    const [preview, setPreview] = useState<string | null>(null);

    // A previa do arquivo escolhido e uma object URL, que precisa ser liberada.
    useEffect(() => {
        if (!file) {
            setPreview(null);
            return;
        }

        const url = URL.createObjectURL(file);
        setPreview(url);

        return () => URL.revokeObjectURL(url);
    }, [file]);

    const shown = preview ?? (removed ? null : (existingUrl ?? null));
    const open = () => inputRef.current?.click();

    return (
        <div className="flex items-center gap-4">
            <button
                type="button"
                onClick={open}
                aria-label={shown ? 'Trocar foto' : 'Enviar foto'}
                className="group focus-visible:ring-primary/20 relative rounded-full focus-visible:ring-2 focus-visible:outline-hidden"
            >
                {renderAvatar(shown)}
                <span className="bg-foreground/55 absolute inset-0 flex items-center justify-center rounded-full opacity-0 transition-opacity group-hover:opacity-100">
                    <Camera className="text-background size-5" />
                </span>
            </button>

            <div className="space-y-1.5">
                <div className="flex flex-wrap items-center gap-2">
                    <Button type="button" variant="outline" size="sm" onClick={open}>
                        <Upload />
                        {shown ? 'Trocar' : 'Enviar foto'}
                    </Button>

                    {shown && (
                        <Button type="button" variant="ghost" size="sm" onClick={onRemove}>
                            <Trash2 />
                            Remover
                        </Button>
                    )}
                </div>

                <p className="text-muted-foreground text-xs">{hint}</p>
            </div>

            <input
                ref={inputRef}
                type="file"
                accept="image/*"
                className="hidden"
                onChange={(event) => {
                    onSelect(event.target.files?.[0] ?? null);
                    // Zera para que escolher o mesmo arquivo de novo dispare o change.
                    event.target.value = '';
                }}
            />
        </div>
    );
}
