import { Form, usePage } from '@inertiajs/react';
import { useRef } from 'react';
import ProfileController from '@/actions/App/Http/Controllers/Settings/ProfileController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

export default function DeleteUser() {
    const confirmationInput = useRef<HTMLInputElement>(null);
    const { auth } = usePage().props;
    const hasPassword = auth.user.tem_senha;

    return (
        <div className="space-y-6">
            <Heading
                variant="small"
                title="Excluir conta"
                description="Exclua sua conta e todos os seus recursos"
            />
            <div className="space-y-4 rounded-lg border border-red-100 bg-red-50 p-4">
                <div className="relative space-y-0.5 text-red-600">
                    <p className="font-medium">Atenção</p>
                    <p className="text-sm">
                        Prossiga com cuidado, esta ação não pode ser desfeita.
                    </p>
                </div>

                <Dialog>
                    <DialogTrigger asChild>
                        <Button
                            variant="destructive"
                            data-test="delete-user-button"
                        >
                            Excluir conta
                        </Button>
                    </DialogTrigger>
                    <DialogContent>
                        <DialogTitle>
                            Tem certeza de que deseja excluir sua conta?
                        </DialogTitle>
                        <DialogDescription>
                            Depois que sua conta for excluída, todos os seus
                            recursos e dados também serão excluídos
                            permanentemente.{' '}
                            {hasPassword
                                ? 'Informe sua senha para confirmar que deseja excluir permanentemente sua conta.'
                                : 'Digite seu endereço de e-mail para confirmar que deseja excluir permanentemente sua conta.'}
                        </DialogDescription>

                        <Form
                            {...ProfileController.destroy.form()}
                            options={{
                                preserveScroll: true,
                            }}
                            onError={() => confirmationInput.current?.focus()}
                            resetOnSuccess
                            className="space-y-6"
                        >
                            {({ resetAndClearErrors, processing, errors }) => (
                                <>
                                    {hasPassword ? (
                                        <div className="grid gap-2">
                                            <Label
                                                htmlFor="password"
                                                className="sr-only"
                                            >
                                                Senha
                                            </Label>

                                            <PasswordInput
                                                id="password"
                                                name="password"
                                                ref={confirmationInput}
                                                placeholder="Senha"
                                                autoComplete="current-password"
                                            />

                                            <InputError
                                                message={errors.password}
                                            />
                                        </div>
                                    ) : (
                                        <div className="grid gap-2">
                                            <Label
                                                htmlFor="confirmacao"
                                                className="sr-only"
                                            >
                                                E-mail
                                            </Label>

                                            <Input
                                                id="confirmacao"
                                                name="confirmacao"
                                                ref={confirmationInput}
                                                placeholder={auth.user.email}
                                                autoComplete="off"
                                            />

                                            <InputError
                                                message={errors.confirmacao}
                                            />
                                        </div>
                                    )}

                                    <DialogFooter className="gap-2">
                                        <DialogClose asChild>
                                            <Button
                                                variant="secondary"
                                                onClick={() =>
                                                    resetAndClearErrors()
                                                }
                                            >
                                                Cancelar
                                            </Button>
                                        </DialogClose>

                                        <Button
                                            variant="destructive"
                                            disabled={processing}
                                            asChild
                                        >
                                            <button
                                                type="submit"
                                                data-test="confirm-delete-user-button"
                                            >
                                                Excluir conta
                                            </button>
                                        </Button>
                                    </DialogFooter>
                                </>
                            )}
                        </Form>
                    </DialogContent>
                </Dialog>
            </div>
        </div>
    );
}
