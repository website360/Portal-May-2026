import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { cn } from '@/lib/utils';

/**
 * Tons de fallback tirados da paleta de graficos, para clientes sem foto ficarem
 * distinguiveis entre si sem sair da identidade do sistema.
 */
const TONES = [
    'bg-primary/10 text-primary',
    'bg-chart-2/10 text-chart-2',
    'bg-success/10 text-success',
    'bg-chart-5/10 text-chart-5',
    'bg-warning/10 text-warning',
];

/**
 * Termos que nao identificam ninguem: formas societarias, ramos genericos,
 * particulas e titulos. Sem filtrar isso, "Alcantara Comercial Ltda." e
 * "Amaral Comercial Ltda." virariam ambos "AL"/"AL" — inicial da primeira e da
 * ultima palavra.
 */
const NOISE = new Set([
    'ltda',
    'ltda.',
    'me',
    'epp',
    'eireli',
    'sa',
    's.a.',
    's/a',
    'cia',
    'cia.',
    'comercial',
    'associados',
    'filhos',
    'e',
    'da',
    'de',
    'do',
    'das',
    'dos',
    'dr',
    'dr.',
    'dra',
    'dra.',
    'sr',
    'sr.',
    'sra',
    'sra.',
    'srta',
    'srta.',
]);

/**
 * Duas letras a partir das palavras que realmente identificam o cliente. Com
 * duas ou mais palavras uteis, usa a inicial das duas primeiras ("Bezerra e
 * Quintana" -> BQ). Com uma so, usa as duas primeiras letras dela
 * ("Alcantara Comercial Ltda." -> AL, "Amaral Comercial Ltda." -> AM).
 */
export function clientInitials(name: string): string {
    const words = name
        .trim()
        .split(/\s+/)
        .filter((word) => word && !NOISE.has(word.toLowerCase()));

    if (words.length === 0) {
        return name.trim().slice(0, 2).toUpperCase() || '?';
    }

    if (words.length === 1) {
        return words[0].slice(0, 2).toUpperCase();
    }

    return (words[0][0] + words[1][0]).toUpperCase();
}

/** Hash estavel: o mesmo cliente sempre recebe o mesmo tom. */
function toneFor(name: string): string {
    let hash = 0;

    for (let i = 0; i < name.length; i++) {
        hash = (hash + name.charCodeAt(i) * (i + 1)) % 997;
    }

    return TONES[hash % TONES.length];
}

interface ClientAvatarProps {
    name: string;
    photoUrl?: string | null;
    className?: string;
}

export function ClientAvatar({ name, photoUrl, className }: ClientAvatarProps) {
    return (
        // O tamanho das iniciais vem da raiz, entao `size-16 text-base` escala tudo junto.
        <Avatar className={cn('size-9 border text-xs', className)}>
            {photoUrl && <AvatarImage src={photoUrl} alt={name} className="object-cover" />}
            <AvatarFallback className={cn('font-semibold', toneFor(name))}>{clientInitials(name)}</AvatarFallback>
        </Avatar>
    );
}
