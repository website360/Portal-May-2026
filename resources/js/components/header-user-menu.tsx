import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { DropdownMenu, DropdownMenuContent, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { UserMenuContent } from '@/components/user-menu-content';
import { useInitials } from '@/hooks/use-initials';
import { type SharedData } from '@/types';
import { usePage } from '@inertiajs/react';
import { ChevronDown } from 'lucide-react';

/**
 * Menu da conta, no canto superior direito.
 *
 * O nome some em telas estreitas — no cabeçalho o espaco disputa com as
 * breadcrumbs, e o avatar sozinho ja identifica quem esta logado.
 */
export function HeaderUserMenu() {
    const { auth } = usePage<SharedData>().props;
    const getInitials = useInitials();

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <button
                    type="button"
                    aria-label="Menu da conta"
                    className="hover:bg-accent focus-visible:ring-ring data-[state=open]:bg-accent flex h-9 items-center gap-2 rounded-md px-1.5 transition-colors focus-visible:ring-2 focus-visible:outline-none"
                >
                    <Avatar className="size-7 overflow-hidden rounded-full">
                        {auth.user.avatar && <AvatarImage src={auth.user.avatar} alt={auth.user.name} className="object-cover" />}
                        <AvatarFallback className="rounded-full bg-neutral-200 text-xs text-black dark:bg-neutral-700 dark:text-white">
                            {getInitials(auth.user.name)}
                        </AvatarFallback>
                    </Avatar>

                    <span className="hidden max-w-40 truncate text-sm font-medium sm:block">{auth.user.name}</span>
                    <ChevronDown className="text-muted-foreground size-4 shrink-0" />
                </button>
            </DropdownMenuTrigger>

            <DropdownMenuContent className="min-w-56 rounded-lg" align="end" side="bottom">
                <UserMenuContent user={auth.user} />
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
