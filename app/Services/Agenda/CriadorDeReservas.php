<?php

namespace App\Services\Agenda;

use App\Models\Espaco;
use App\Models\Grupo;
use App\Models\Reserva;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Grava reservas (uma ou uma série) para o dono da agenda. Usado pela tela de reservas e pela confirmação do assistente.
 *
 * Toda data é conferida dentro da mesma transação, com a agenda do dono travada. Qualquer conflito desfaz tudo e lança ConflitosDeReservaException.
 */
class CriadorDeReservas
{
    public function __construct(private readonly VerificadorDeDisponibilidade $verificador) {}

    /**
     * @param  array{titulo: string, inicio: string, fim: string, pessoas?: int|null, observacoes?: string|null, grupo_id?: string|null}  $dados
     * @param  list<CarbonImmutable>  $datas
     * @param  list<array{id: string, quantidade: int}>  $equipamentos
     * @return list<Reserva>
     *
     * @throws ConflitosDeReservaException
     */
    public function criar(User $usuario, Espaco $espaco, ?Grupo $grupo, array $dados, array $datas, array $equipamentos): array
    {
        $serieId = count($datas) > 1 ? (string) Str::uuid() : null;

        return DB::transaction(function () use ($usuario, $espaco, $grupo, $dados, $datas, $equipamentos, $serieId): array {
            $this->travarAgendaDo($usuario);

            $conflitos = [];

            foreach ($datas as $data) {
                $conflitosDaData = $this->conflitosDaData($espaco, $data, $dados, $equipamentos, null);

                if ($conflitosDaData !== null) {
                    $conflitos[] = $conflitosDaData;
                }
            }

            if ($conflitos !== []) {
                throw new ConflitosDeReservaException($conflitos);
            }

            $reservas = [];

            foreach ($datas as $data) {
                $reserva = $usuario->reservas()->create([
                    'espaco_id' => $espaco->id,
                    'grupo_id' => $grupo?->id,
                    'titulo' => $dados['titulo'],
                    'data' => $data->toDateString(),
                    'inicio' => $dados['inicio'],
                    'fim' => $dados['fim'],
                    'pessoas' => $dados['pessoas'] ?? null,
                    'observacoes' => $dados['observacoes'] ?? null,
                    'serie_id' => $serieId,
                ]);

                $reserva->equipamentos()->attach($this->pivotDosEquipamentos($equipamentos));

                $reservas[] = $reserva;
            }

            return $reservas;
        });
    }

    /**
     * Confere uma data candidata. Devolve null quando não há conflito.
     *
     * O espaço inativo só conta como conflito quando a reserva passa a usá-lo: na edição sem troca de espaço, `ignorarEspacoInativo` o descarta.
     *
     * @param  array<string, mixed>  $dados  Com `inicio` e `fim`.
     * @param  list<array{id: string, quantidade: int}>  $equipamentos
     * @return array{data: string, conflitos: list<array{tipo: string, mensagem: string}>}|null
     */
    public function conflitosDaData(Espaco $espaco, CarbonImmutable $data, array $dados, array $equipamentos, ?string $ignorarReservaId, bool $ignorarEspacoInativo = false): ?array
    {
        $conflitos = $this->verificador->verificar($espaco, $data, $dados['inicio'], $dados['fim'], $equipamentos, $ignorarReservaId);

        if ($ignorarEspacoInativo) {
            $conflitos = array_values(array_filter($conflitos, fn (Conflito $conflito) => $conflito->tipo !== Conflito::ESPACO_INATIVO));
        }

        if ($conflitos === []) {
            return null;
        }

        return [
            'data' => $data->toDateString(),
            'conflitos' => array_map(fn (Conflito $conflito) => $conflito->toArray(), $conflitos),
        ];
    }

    /**
     * Trava a linha do dono até o fim da transação, para que duas gravações da mesma agenda não passem juntas pela verificação.
     */
    public function travarAgendaDo(User $usuario): void
    {
        User::query()->whereKey($usuario->id)->lockForUpdate()->firstOrFail();
    }

    /**
     * @param  list<array{id: string, quantidade: int}>  $equipamentos
     * @return array<string, array{quantidade: int}>
     */
    public function pivotDosEquipamentos(array $equipamentos): array
    {
        return collect($equipamentos)
            ->mapWithKeys(fn (array $equipamento) => [$equipamento['id'] => ['quantidade' => $equipamento['quantidade']]])
            ->all();
    }
}
