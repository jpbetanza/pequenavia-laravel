<?php

namespace App\Services\Agenda;

use App\Enums\EscopoDaReserva;
use App\Models\Espaco;
use App\Models\Grupo;
use App\Models\Reserva;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Edita ou exclui reservas, só a escolhida ou ela e as próximas da série. Usado pelo calendário e pelas propostas do assistente.
 *
 * A edição é gravada e conferida dentro da transação, com a agenda do dono travada: um conflito desfaz tudo.
 */
final class EdicaoDeReservas
{
    public function __construct(private readonly CriadorDeReservas $criador) {}

    /**
     * Aplica a mesma mudança a cada reserva atingida. A data muda só na reserva escolhida; as da série mantêm a sua.
     *
     * @param  array<string, mixed>  $dados  titulo, data, inicio, fim, pessoas e observacoes
     * @param  list<array{id: string, quantidade: int}>  $equipamentos
     * @return Collection<int, Reserva> reservas alteradas
     *
     * @throws ConflitosDeReservaException
     */
    public function atualizar(User $usuario, Reserva $reserva, Espaco $espaco, ?Grupo $grupo, array $dados, array $equipamentos, EscopoDaReserva $escopo): Collection
    {
        $membros = $this->membrosDaSerie($usuario, $reserva, $escopo);
        $espacosOriginais = $membros->mapWithKeys(fn (Reserva $membro) => [$membro->id => $membro->espaco_id])->all();

        DB::transaction(function () use ($usuario, $reserva, $espaco, $grupo, $dados, $equipamentos, $membros, $espacosOriginais): void {
            $this->criador->travarAgendaDo($usuario);

            foreach ($membros as $membro) {
                $ehEsta = $membro->is($reserva);

                $membro->fill([
                    'espaco_id' => $espaco->id,
                    'grupo_id' => $grupo?->id,
                    'titulo' => $dados['titulo'],
                    'inicio' => $dados['inicio'],
                    'fim' => $dados['fim'],
                    'pessoas' => $dados['pessoas'] ?? null,
                    'observacoes' => $dados['observacoes'] ?? null,
                    ...($ehEsta ? ['data' => $dados['data']] : []),
                ])->save();

                $membro->equipamentos()->sync($this->criador->pivotDosEquipamentos($equipamentos));
            }

            $conflitos = [];

            foreach ($membros as $membro) {
                $conflitosDaData = $this->criador->conflitosDaData(
                    $espaco,
                    CarbonImmutable::instance($membro->data)->startOfDay(),
                    [
                        'inicio' => $dados['inicio'],
                        'fim' => $dados['fim'],
                    ],
                    $equipamentos,
                    $membro->id,
                    ignorarEspacoInativo: $espacosOriginais[$membro->id] === $espaco->id,
                );

                if ($conflitosDaData !== null) {
                    $conflitos[] = $conflitosDaData;
                }
            }

            if ($conflitos !== []) {
                throw new ConflitosDeReservaException($conflitos);
            }
        });

        return $membros;
    }

    /**
     * Exclui as reservas atingidas. Os equipamentos saem junto (cascade).
     *
     * @return Collection<int, Reserva> reservas excluídas
     */
    public function excluir(User $usuario, Reserva $reserva, EscopoDaReserva $escopo): Collection
    {
        $membros = $this->membrosDaSerie($usuario, $reserva, $escopo);

        Reserva::query()->whereIn('id', $membros->pluck('id'))->delete();

        return $membros;
    }

    /**
     * Reservas que a edição ou a exclusão atinge: só a própria, ou a série a partir da data dela.
     *
     * @return Collection<int, Reserva>
     */
    public function membrosDaSerie(User $usuario, Reserva $reserva, EscopoDaReserva $escopo): Collection
    {
        if ($escopo === EscopoDaReserva::SoEsta || $reserva->serie_id === null) {
            return collect([$reserva]);
        }

        return Reserva::query()
            ->where('user_id', $usuario->id)
            ->where('serie_id', $reserva->serie_id)
            ->where('data', '>=', $reserva->data->toDateString())
            ->orderBy('data')
            ->get();
    }
}
