<?php

namespace App\Services\PrimeirosPassos;

use App\Enums\PapelDaMensagem;
use App\Models\Mensagem;
use App\Models\User;
use App\Notifications\ContinuarPrimeirosPassos;
use App\Services\Push\Notificador;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Lembra quem começou a configurar a agenda e parou no meio: um aviso por push e por e-mail com o próximo passo.
 * No máximo dois lembretes por pessoa, com alguns dias entre eles, e nunca enquanto ela está usando o app.
 */
class LembreteDosPrimeirosPassos
{
    /** Tempo sem atividade antes do primeiro lembrete. */
    public const HORAS_PARADA = 20;

    /** Depois disso sem atividade, desiste: a pessoa provavelmente não volta por um lembrete. */
    public const DIAS_LIMITE = 14;

    public const DIAS_ENTRE_LEMBRETES = 3;

    public const MAX_LEMBRETES = 2;

    /** Próximo passo, em uma frase que diz o que fazer. */
    private const PROXIMO_PASSO = [
        'espacos' => 'Falta cadastrar os espaços. Mande só os nomes das salas ao assistente que ele prepara o cadastro para você confirmar.',
        'equipamentos' => 'Falta cadastrar os equipamentos. Diga ao assistente quais são e quantos tem de cada um.',
        'grupos' => 'Faltam os grupos e pastorais. Mande os nomes ao assistente, mesmo sem os contatos.',
        'formulario' => 'Falta mandar aos grupos o link do formulário de pedidos. O assistente escreve a mensagem para o WhatsApp.',
        'avisos' => 'Falta ativar os avisos no celular, para saber na hora quando chega um pedido.',
    ];

    public function __construct(
        private readonly PrimeirosPassos $primeirosPassos,
        private readonly Notificador $notificador,
    ) {}

    /**
     * Quem deve receber um lembrete agora, com o passo em que parou.
     *
     * @return Collection<int, array{usuario: User, passo: string}>
     */
    public function pendentes(): Collection
    {
        $agora = CarbonImmutable::now();

        return User::query()
            ->where('admin', false)
            ->whereNotNull('primeiros_passos->iniciado_em')
            ->whereNull('primeiros_passos->dispensado_em')
            ->get()
            ->map(function (User $usuario) use ($agora): ?array {
                $guardado = $usuario->primeiros_passos ?? [];

                if ((int) ($guardado['lembretes'] ?? 0) >= self::MAX_LEMBRETES) {
                    return null;
                }

                if (isset($guardado['lembrado_em']) && CarbonImmutable::parse($guardado['lembrado_em'])->greaterThan($agora->subDays(self::DIAS_ENTRE_LEMBRETES))) {
                    return null;
                }

                $ultima = $this->ultimaAtividade($usuario);

                if ($ultima->greaterThan($agora->subHours(self::HORAS_PARADA)) || $ultima->lessThan($agora->subDays(self::DIAS_LIMITE))) {
                    return null;
                }

                $estado = $this->primeirosPassos->estado($usuario);

                if ($estado === null || $estado['fase'] === 'concluido' || $estado['adiado'] || $estado['atual'] === null) {
                    return null;
                }

                return ['usuario' => $usuario, 'passo' => $estado['atual']];
            })
            ->filter()
            ->values();
    }

    /**
     * Manda o lembrete por push (se houver aparelho) e por e-mail (se verificado) e anota o envio.
     */
    public function enviar(User $usuario, string $passo): void
    {
        $texto = self::PROXIMO_PASSO[$passo] ?? 'Falta pouco para a agenda ficar pronta.';

        $this->notificador->notificar(
            $usuario,
            'Sua agenda está quase pronta',
            $texto,
            route('inicio', absolute: false),
            'primeiros-passos',
        );

        if ($usuario->hasVerifiedEmail()) {
            $usuario->notify(new ContinuarPrimeirosPassos($texto));
        }

        $this->primeirosPassos->registrarLembrete($usuario);
    }

    /**
     * A mais recente entre a última mensagem dela ao assistente, o começo do roteiro e o último lembrete.
     */
    private function ultimaAtividade(User $usuario): CarbonImmutable
    {
        $guardado = $usuario->primeiros_passos ?? [];

        $mensagem = Mensagem::query()
            ->whereHas('conversa', fn ($conversa) => $conversa->where('user_id', $usuario->id))
            ->where('papel', PapelDaMensagem::Usuario)
            ->max('created_at');

        return collect([$mensagem, $guardado['iniciado_em'] ?? null, $guardado['lembrado_em'] ?? null])
            ->filter()
            ->map(fn ($data) => CarbonImmutable::parse((string) $data))
            ->sortDesc()
            ->first() ?? CarbonImmutable::parse((string) $usuario->created_at);
    }
}
