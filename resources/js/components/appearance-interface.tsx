import { SegmentedControl } from '@/components/ui/segmented-control';
import { RADII, SCALES, usePersonalization } from '@/hooks/use-personalization';

/** Tamanho da interface e arredondamento dos cantos. Por usuário, aplica na hora. */
export default function AppearanceInterface() {
    const { scale, radius, setScale, setRadius } = usePersonalization();

    return (
        <div className="grid gap-5 sm:max-w-md">
            <div className="space-y-2">
                <p className="text-sm font-medium">Tamanho da interface</p>
                <SegmentedControl
                    aria-label="Tamanho da interface"
                    value={scale}
                    onChange={(v) => setScale(v as (typeof SCALES)[number]['value'])}
                    options={SCALES.map((s) => ({ value: s.value, label: s.label }))}
                />
            </div>

            <div className="space-y-2">
                <p className="text-sm font-medium">Cantos</p>
                <SegmentedControl
                    aria-label="Arredondamento dos cantos"
                    value={radius}
                    onChange={(v) => setRadius(v as (typeof RADII)[number]['value'])}
                    options={RADII.map((r) => ({ value: r.value, label: r.label }))}
                />
            </div>
        </div>
    );
}
