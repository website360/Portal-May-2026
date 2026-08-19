import { useCallback, useEffect, useState } from 'react';

/*
  Personalização por usuário — separada de claro/escuro (use-appearance).
  Três eixos, todos guardados no localStorage e aplicados no <html>:
    - accent: cor de destaque (sobrescreve --primary e derivados via data-accent)
    - scale:  tamanho da interface (font-size da raiz; a UI é toda rem)
    - radius: arredondamento dos cantos (--radius)
  A aplicação acontece antes do render (initializePersonalization) para não piscar.
*/

export type Accent = 'blue' | 'violet' | 'emerald' | 'rose' | 'amber' | 'sky';
export type UiScale = 'compact' | 'default' | 'comfortable' | 'large';
export type UiRadius = 'square' | 'default' | 'round';

export const ACCENTS: { value: Accent; label: string; swatch: string }[] = [
    { value: 'blue', label: 'Azul', swatch: 'hsl(221 83% 53%)' },
    { value: 'violet', label: 'Violeta', swatch: 'hsl(262 83% 58%)' },
    { value: 'emerald', label: 'Esmeralda', swatch: 'hsl(160 84% 39%)' },
    { value: 'rose', label: 'Rosé', swatch: 'hsl(347 77% 50%)' },
    { value: 'amber', label: 'Âmbar', swatch: 'hsl(32 95% 44%)' },
    { value: 'sky', label: 'Ciano', swatch: 'hsl(199 89% 48%)' },
];

export const SCALES: { value: UiScale; label: string; px: string }[] = [
    { value: 'compact', label: 'Compacto', px: '16px' },
    { value: 'default', label: 'Padrão', px: '17px' },
    { value: 'comfortable', label: 'Confortável', px: '18px' },
    { value: 'large', label: 'Grande', px: '19px' },
];

export const RADII: { value: UiRadius; label: string; rem: string }[] = [
    { value: 'square', label: 'Reto', rem: '0.35rem' },
    { value: 'default', label: 'Padrão', rem: '0.65rem' },
    { value: 'round', label: 'Arredondado', rem: '0.95rem' },
];

const DEFAULTS = { accent: 'blue' as Accent, scale: 'default' as UiScale, radius: 'default' as UiRadius };

const scalePx = (v: UiScale) => SCALES.find((s) => s.value === v)?.px ?? '17px';
const radiusRem = (v: UiRadius) => RADII.find((r) => r.value === v)?.rem ?? '0.65rem';

const read = <T,>(key: string, allowed: readonly string[], fallback: T): T => {
    const v = localStorage.getItem(key);
    return v && allowed.includes(v) ? (v as T) : fallback;
};

const applyAccent = (accent: Accent) => {
    const root = document.documentElement;
    if (accent === 'blue') root.removeAttribute('data-accent');
    else root.setAttribute('data-accent', accent);
};

const applyScale = (scale: UiScale) => {
    document.documentElement.style.fontSize = scalePx(scale);
};

const applyRadius = (radius: UiRadius) => {
    document.documentElement.style.setProperty('--radius', radiusRem(radius));
};

export function initializePersonalization() {
    applyAccent(read('accent', ACCENTS.map((a) => a.value), DEFAULTS.accent));
    applyScale(read('ui-scale', SCALES.map((s) => s.value), DEFAULTS.scale));
    applyRadius(read('ui-radius', RADII.map((r) => r.value), DEFAULTS.radius));
}

export function usePersonalization() {
    const [accent, setAccentState] = useState<Accent>(DEFAULTS.accent);
    const [scale, setScaleState] = useState<UiScale>(DEFAULTS.scale);
    const [radius, setRadiusState] = useState<UiRadius>(DEFAULTS.radius);

    useEffect(() => {
        setAccentState(read('accent', ACCENTS.map((a) => a.value), DEFAULTS.accent));
        setScaleState(read('ui-scale', SCALES.map((s) => s.value), DEFAULTS.scale));
        setRadiusState(read('ui-radius', RADII.map((r) => r.value), DEFAULTS.radius));
    }, []);

    const setAccent = useCallback((v: Accent) => {
        setAccentState(v);
        localStorage.setItem('accent', v);
        applyAccent(v);
    }, []);

    const setScale = useCallback((v: UiScale) => {
        setScaleState(v);
        localStorage.setItem('ui-scale', v);
        applyScale(v);
    }, []);

    const setRadius = useCallback((v: UiRadius) => {
        setRadiusState(v);
        localStorage.setItem('ui-radius', v);
        applyRadius(v);
    }, []);

    return { accent, scale, radius, setAccent, setScale, setRadius };
}
