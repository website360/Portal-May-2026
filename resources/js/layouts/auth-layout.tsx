import AppLogoIcon from '@/components/app-logo-icon';
import { Card, CardContent } from '@/components/ui/card';

interface AuthLayoutProps {
    children: React.ReactNode;
    title: string;
    description: string;
}

/**
 * Casca das telas de autenticacao: card centralizado sobre as superficies
 * especiais do design system (.bg-grid + .bg-radial-primary).
 */
export default function AuthLayout({ children, title, description }: AuthLayoutProps) {
    return (
        <div className="bg-background relative flex min-h-svh flex-col items-center justify-center overflow-hidden p-6">
            <div className="bg-grid pointer-events-none absolute inset-0 opacity-70" aria-hidden />
            <div className="bg-radial-primary pointer-events-none absolute inset-0" aria-hidden />

            <div className="animate-fade-in relative w-full max-w-md">
                <div className="mb-8 flex flex-col items-center gap-4">
                    <div className="bg-primary text-primary-foreground shadow-glow flex size-12 items-center justify-center rounded-xl">
                        <AppLogoIcon className="size-7" />
                    </div>

                    <div className="space-y-1.5 text-center">
                        <h1 className="text-gradient text-2xl font-bold tracking-tight">{title}</h1>
                        <p className="text-muted-foreground text-sm">{description}</p>
                    </div>
                </div>

                <Card className="shadow-lg">
                    <CardContent className="p-6 sm:p-8">{children}</CardContent>
                </Card>

                <p className="text-muted-foreground mt-6 text-center text-xs">Sistema May · uso interno da agência</p>
            </div>
        </div>
    );
}
