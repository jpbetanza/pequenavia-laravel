import { Form, Head } from '@inertiajs/react';
import InputError from '@/components/input-error';
import GoogleButton from '@/components/google-button';
import PasswordInput from '@/components/password-input';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { register } from '@/routes';
import { store } from '@/routes/login';
import { request } from '@/routes/password';
import PasskeyVerify from '@/components/passkey-verify';

type Props = {
    status?: string;
    canResetPassword: boolean;
};

export default function Login({ status, canResetPassword }: Props) {
    return (
        <>
            <Head title="Entrar" />

            <p className="text-sm text-muted-foreground">
                Ainda não tem conta?{' '}
                <TextLink href={register()} className="font-semibold">
                    Criar conta
                </TextLink>
            </p>

            <GoogleButton />

            <div className="flex items-center gap-3.5 text-[13px] text-muted-foreground">
                <span className="h-px flex-1 bg-border" />
                ou com e-mail
                <span className="h-px flex-1 bg-border" />
            </div>

            <Form
                {...store.form()}
                resetOnSuccess={['password']}
                className="flex flex-col gap-5"
            >
                {({ processing, errors }) => (
                    <>
                        <div className="grid gap-5">
                            <div className="grid gap-2">
                                <Label htmlFor="email">E-mail</Label>
                                <Input
                                    id="email"
                                    type="email"
                                    name="email"
                                    required
                                    autoFocus
                                    tabIndex={1}
                                    autoComplete="email"
                                    placeholder="secretaria@paroquia.org.br"
                                    className="h-12 rounded-[10px] bg-card px-3.5"
                                />
                                <InputError message={errors.email} />
                            </div>

                            <div className="grid gap-2">
                                <div className="flex items-baseline justify-between">
                                    <Label htmlFor="password">Senha</Label>
                                    {canResetPassword && (
                                        <TextLink
                                            href={request()}
                                            className="inline-flex min-h-11 items-center text-sm"
                                            tabIndex={5}
                                        >
                                            Esqueceu a senha?
                                        </TextLink>
                                    )}
                                </div>
                                <PasswordInput
                                    id="password"
                                    name="password"
                                    required
                                    tabIndex={2}
                                    autoComplete="current-password"
                                    placeholder="Senha"
                                    className="h-12 rounded-[10px] bg-card px-3.5"
                                />
                                <InputError message={errors.password} />
                            </div>

                            <div className="flex min-h-11 items-center gap-2.5">
                                <Checkbox
                                    id="remember"
                                    name="remember"
                                    tabIndex={3}
                                />
                                <Label
                                    htmlFor="remember"
                                    className="text-[15px]"
                                >
                                    Lembrar de mim neste computador
                                </Label>
                            </div>

                            <Button
                                type="submit"
                                className="h-[52px] w-full rounded-xl text-[17px] font-semibold"
                                tabIndex={4}
                                disabled={processing}
                                data-test="login-button"
                            >
                                {processing && <Spinner />}
                                Entrar
                            </Button>
                        </div>
                    </>
                )}
            </Form>

            <PasskeyVerify variant="ghost" showSeparator={false} />

            {status && (
                <div className="text-center text-sm font-medium text-sucesso">
                    {status}
                </div>
            )}
        </>
    );
}

Login.layout = {
    title: 'Entrar',
    painel: 'Bem-vindo de volta à secretaria.',
};
