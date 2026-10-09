import { Button } from '@/components/ui/button';
import { google } from '@/routes/auth';

type Props = {
    label?: string;
};

/**
 * O OAuth sai do app e volta pelo callback, então precisa de navegação completa:
 * um <a> comum, nunca o <Link> do Inertia.
 */
export default function GoogleButton({
    label = 'Continuar com o Google',
}: Props) {
    return (
        <Button asChild variant="outline" className="w-full">
            <a href={google.url()} data-test="google-button">
                {label}
            </a>
        </Button>
    );
}
