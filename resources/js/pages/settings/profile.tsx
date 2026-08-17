import { type BreadcrumbItem, type SharedData } from '@/types';
import { Transition } from '@headlessui/react';
import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { FormEventHandler } from 'react';

import DeleteUser from '@/components/delete-user';
import HeadingSmall from '@/components/heading-small';
import InputError from '@/components/input-error';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { PhotoPicker } from '@/components/ui/photo-picker';
import { useInitials } from '@/hooks/use-initials';
import AppLayout from '@/layouts/app-layout';
import SettingsLayout from '@/layouts/settings/layout';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Configurações', href: '/configuracoes/perfil' },
    { title: 'Perfil', href: '/configuracoes/perfil' },
];

export default function Profile({ mustVerifyEmail, status }: { mustVerifyEmail: boolean; status?: string }) {
    const { auth } = usePage<SharedData>().props;
    const getInitials = useInitials();

    const { data, setData, post, errors, processing, recentlySuccessful } = useForm<{
        _method: string;
        name: string;
        email: string;
        photo: File | null;
        remove_photo: string;
    }>({
        // Upload exige multipart, e o PHP não lê multipart em PATCH — daí o POST
        // com o método falseado.
        _method: 'patch',
        name: auth.user.name,
        email: auth.user.email,
        photo: null,
        remove_photo: '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();

        post(route('profile.update'), { preserveScroll: true });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Perfil" />

            <SettingsLayout>
                <div className="space-y-6">
                    <HeadingSmall title="Informações do perfil" description="Sua foto, nome e e-mail de acesso" />

                    <form onSubmit={submit} className="space-y-6">
                        <div className="grid gap-2">
                            <Label>Foto</Label>

                            <PhotoPicker
                                file={data.photo}
                                existingUrl={auth.user.avatar}
                                removed={data.remove_photo === '1'}
                                onSelect={(file) => {
                                    setData('photo', file);
                                    setData('remove_photo', '');
                                }}
                                onRemove={() => {
                                    // Descarta primeiro o arquivo recém-escolhido; só
                                    // depois marca a foto já gravada para exclusão.
                                    if (data.photo) {
                                        setData('photo', null);
                                    } else {
                                        setData('remove_photo', '1');
                                    }
                                }}
                                renderAvatar={(url) => (
                                    <Avatar className="size-16 border text-base">
                                        {url && <AvatarImage src={url} alt={data.name} className="object-cover" />}
                                        <AvatarFallback className="bg-neutral-200 font-semibold text-black dark:bg-neutral-700 dark:text-white">
                                            {getInitials(data.name)}
                                        </AvatarFallback>
                                    </Avatar>
                                )}
                            />

                            <InputError className="mt-2" message={errors.photo} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="name">Nome</Label>

                            <Input
                                id="name"
                                className="mt-1 block w-full"
                                value={data.name}
                                onChange={(e) => setData('name', e.target.value)}
                                required
                                autoComplete="name"
                                placeholder="Nome completo"
                            />

                            <InputError className="mt-2" message={errors.name} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="email">E-mail</Label>

                            <Input
                                id="email"
                                type="email"
                                className="mt-1 block w-full"
                                value={data.email}
                                onChange={(e) => setData('email', e.target.value)}
                                required
                                autoComplete="username"
                                placeholder="voce@agenciamay.com.br"
                            />

                            <InputError className="mt-2" message={errors.email} />
                        </div>

                        {mustVerifyEmail && auth.user.email_verified_at === null && (
                            <div>
                                <p className="text-muted-foreground mt-2 text-sm">
                                    Seu e-mail ainda não foi confirmado.{' '}
                                    <Link
                                        href={route('verification.send')}
                                        method="post"
                                        as="button"
                                        className="hover:text-foreground text-sm underline"
                                    >
                                        Reenviar o link de confirmação.
                                    </Link>
                                </p>

                                {status === 'verification-link-sent' && (
                                    <div className="text-success mt-2 text-sm font-medium">
                                        Enviamos um novo link de confirmação para o seu e-mail.
                                    </div>
                                )}
                            </div>
                        )}

                        <div className="flex items-center gap-4">
                            <Button disabled={processing}>Salvar</Button>

                            <Transition
                                show={recentlySuccessful}
                                enter="transition ease-in-out"
                                enterFrom="opacity-0"
                                leave="transition ease-in-out"
                                leaveTo="opacity-0"
                            >
                                <p className="text-muted-foreground text-sm">Salvo</p>
                            </Transition>
                        </div>
                    </form>
                </div>

                <DeleteUser />
            </SettingsLayout>
        </AppLayout>
    );
}
