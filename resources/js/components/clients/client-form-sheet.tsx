import { CLIENT_STEPS, ClientStepFields } from '@/components/clients/client-form-steps';
import { Button } from '@/components/ui/button';
import { Sheet, SheetContent, SheetDescription, SheetHeader, SheetTitle } from '@/components/ui/sheet';
import { cn } from '@/lib/utils';
import { EMPTY_CLIENT_FORM, toFormData, type Client, type ClientFormData } from '@/types/clients';
import { useForm } from '@inertiajs/react';
import { ArrowLeft, ArrowRight, Check } from 'lucide-react';
import { useEffect, useState } from 'react';

interface ClientFormSheetProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    /** Preenchido em edição; nulo em cadastro. */
    client: Client | null;
}

export function ClientFormSheet({ open, onOpenChange, client }: ClientFormSheetProps) {
    const [step, setStep] = useState(0);
    const { data, setData, post, transform, processing, errors, clearErrors, reset, setDefaults } = useForm<ClientFormData>(EMPTY_CLIENT_FORM);

    const isEditing = client !== null;
    const isLastStep = step === CLIENT_STEPS.length - 1;
    const current = CLIENT_STEPS[step];

    // Ao abrir, carrega o cliente (edição) ou zera o formulário (cadastro).
    useEffect(() => {
        if (!open) return;

        const values = client ? toFormData(client) : EMPTY_CLIENT_FORM;

        setDefaults(values);
        reset();
        setData(values);
        clearErrors();
        setStep(0);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open, client?.id]);

    // Erro de validação do servidor: pula para a primeira etapa que o contém.
    useEffect(() => {
        const failed = Object.keys(errors);
        if (failed.length === 0) return;

        const target = CLIENT_STEPS.findIndex((candidate) => candidate.fields.some((field) => failed.includes(field as string)));

        if (target >= 0) setStep(target);
    }, [errors]);

    const missingRequired = current.required.filter((field) => String(data[field] ?? '').trim() === '');

    function goNext() {
        if (missingRequired.length > 0 || isLastStep) return;
        setStep((value) => value + 1);
    }

    function submit(event: React.FormEvent) {
        event.preventDefault();

        const options = {
            preserveScroll: true,
            // Sempre multipart: o formulário carrega a foto, e assim o corpo
            // tem um formato só, com ou sem arquivo escolhido.
            forceFormData: true,
            onSuccess: () => onOpenChange(false),
        };

        if (isEditing) {
            // Não dá para enviar arquivo num PUT: vai como POST com method spoofing.
            transform((values) => ({ ...values, _method: 'put' }));
            post(route('clientes.update', client.id), options);
        } else {
            transform((values) => values);
            post(route('clientes.store'), options);
        }
    }

    return (
        <Sheet open={open} onOpenChange={onOpenChange}>
            <SheetContent side="right" className="flex w-full flex-col gap-0 p-0 sm:max-w-xl">
                <SheetHeader className="space-y-4 border-b p-6 text-left">
                    <div className="space-y-1.5 pr-8">
                        <SheetTitle>{isEditing ? 'Editar cliente' : 'Novo cliente'}</SheetTitle>
                        <SheetDescription>
                            {isEditing ? `Alterando o cadastro de ${client.name}.` : 'O cadastro é dividido em quatro etapas curtas.'}
                        </SheetDescription>
                    </div>

                    <StepIndicator current={step} onSelect={setStep} furthest={isEditing ? CLIENT_STEPS.length - 1 : step} />
                </SheetHeader>

                <form onSubmit={submit} className="flex min-h-0 flex-1 flex-col">
                    <div key={current.id} className="animate-fade-in min-h-0 flex-1 overflow-y-auto p-6">
                        <div className="mb-5">
                            <h3 className="text-sm font-semibold">{current.label}</h3>
                            <p className="text-muted-foreground text-xs">{current.description}</p>
                        </div>

                        <ClientStepFields
                            stepIndex={step}
                            data={data}
                            setData={setData}
                            errors={errors}
                            existingPhotoUrl={client?.photo_url ?? null}
                        />
                    </div>

                    <div className="bg-muted/30 flex items-center justify-between gap-3 border-t p-6">
                        <span className="text-muted-foreground text-xs">
                            Etapa {step + 1} de {CLIENT_STEPS.length}
                        </span>

                        <div className="flex items-center gap-2">
                            {step > 0 && (
                                <Button type="button" variant="outline" onClick={() => setStep((value) => value - 1)}>
                                    <ArrowLeft />
                                    Voltar
                                </Button>
                            )}

                            {isLastStep ? (
                                <Button type="submit" loading={processing}>
                                    {!processing && <Check />}
                                    {isEditing ? 'Salvar alterações' : 'Cadastrar cliente'}
                                </Button>
                            ) : (
                                <Button type="button" onClick={goNext} disabled={missingRequired.length > 0}>
                                    Continuar
                                    <ArrowRight />
                                </Button>
                            )}
                        </div>
                    </div>
                </form>
            </SheetContent>
        </Sheet>
    );
}

/** Trilha das quatro etapas. Etapas já visitadas viram atalho de navegação. */
function StepIndicator({ current, furthest, onSelect }: { current: number; furthest: number; onSelect: (step: number) => void }) {
    return (
        <ol className="flex items-start">
            {CLIENT_STEPS.map((step, index) => {
                const done = index < current;
                const active = index === current;
                const reachable = index <= Math.max(furthest, current);

                return (
                    <li key={step.id} className={cn('flex flex-1 flex-col items-center gap-1.5', index > 0 && 'relative')}>
                        {index > 0 && (
                            <span
                                aria-hidden
                                className={cn('absolute top-3.5 right-1/2 h-0.5 w-full', done || active ? 'bg-primary' : 'bg-border')}
                            />
                        )}

                        <button
                            type="button"
                            disabled={!reachable}
                            onClick={() => reachable && onSelect(index)}
                            aria-current={active ? 'step' : undefined}
                            className={cn(
                                'relative z-10 flex size-7 items-center justify-center rounded-full border text-xs font-semibold transition-all duration-150',
                                'focus-visible:ring-primary/20 focus-visible:ring-2 focus-visible:outline-hidden',
                                reachable && 'active:scale-[0.95]',
                                !reachable && 'cursor-default',
                                done && 'border-primary bg-primary text-primary-foreground',
                                active && 'border-primary bg-primary text-primary-foreground shadow-glow',
                                !done && !active && 'border-border bg-background text-muted-foreground',
                            )}
                        >
                            {done ? <Check className="size-3.5" /> : index + 1}
                        </button>

                        <span
                            className={cn(
                                'text-center text-[0.7rem] leading-tight',
                                active ? 'text-foreground font-medium' : 'text-muted-foreground',
                            )}
                        >
                            {step.label}
                        </span>
                    </li>
                );
            })}
        </ol>
    );
}
