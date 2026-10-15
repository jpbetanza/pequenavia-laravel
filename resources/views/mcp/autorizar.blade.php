{{-- Tela do OAuth do MCP da gestão (Passport). Só contas admin podem autorizar; os demais só podem recusar. --}}
@php($podeAutorizar = $user->can('gestao'))
<!DOCTYPE html>
<html lang="pt-BR">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="color-scheme" content="light">
        <style>
            html {
                background-color: #f5f0e6;
            }
        </style>
        <link rel="icon" href="/favicon.ico" sizes="any">
        <link rel="icon" type="image/png" sizes="96x96" href="/favicon-96.png">
        <title>Conectar {{ $client->name }} - {{ config('app.name') }}</title>
        @fonts
        @vite(['resources/css/app.css'])
    </head>
    <body class="font-sans antialiased bg-background text-foreground">
        <main class="flex min-h-svh items-center justify-center p-4">
            <div class="w-full max-w-md rounded-2xl border bg-card p-6 shadow-sm">
                <div class="mb-5 flex flex-col items-center gap-3 text-center">
                    <img src="/favicon-96.png" alt="" class="size-12">
                    <h1 class="font-serif text-3xl font-bold">Conectar {{ $client->name }}</h1>
                    @if ($podeAutorizar)
                        <p class="text-sm text-muted-foreground">
                            {{ $client->name }} vai poder ler o painel de gestão (números, conversas classificadas com
                            dados pessoais mascarados, temas e clientes) e corrigir classificações e temas em seu nome.
                        </p>
                    @else
                        <p class="text-sm text-muted-foreground">
                            Esta conexão é só para a gestão do {{ config('app.name') }}. Sua conta não tem acesso ao
                            painel de gestão.
                        </p>
                    @endif
                </div>

                <div class="mb-5 rounded-xl bg-muted/60 p-3 text-sm">
                    <span class="text-muted-foreground">Entrou como</span>
                    <span class="font-medium">{{ $user->email }}</span>
                </div>

                <div class="flex gap-3">
                    <form method="POST" action="{{ route('passport.authorizations.deny') }}" class="flex-1">
                        @csrf
                        @method('DELETE')
                        <input type="hidden" name="state" value="">
                        <input type="hidden" name="client_id" value="{{ $client->id }}">
                        <input type="hidden" name="auth_token" value="{{ $authToken }}">
                        <button type="submit" class="h-11 w-full rounded-xl border bg-background text-sm font-medium hover:bg-muted">
                            {{ $podeAutorizar ? 'Cancelar' : 'Voltar' }}
                        </button>
                    </form>

                    @if ($podeAutorizar)
                        <form method="POST" action="{{ route('passport.authorizations.approve') }}" class="flex-1">
                            @csrf
                            <input type="hidden" name="state" value="">
                            <input type="hidden" name="client_id" value="{{ $client->id }}">
                            <input type="hidden" name="auth_token" value="{{ $authToken }}">
                            <button type="submit" class="h-11 w-full rounded-xl bg-primary text-sm font-semibold text-primary-foreground hover:bg-primary/90">
                                Autorizar
                            </button>
                        </form>
                    @endif
                </div>
            </div>
        </main>
    </body>
</html>
