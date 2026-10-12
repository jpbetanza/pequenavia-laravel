<?php

namespace App\Services\Agenda\Cadastros;

use App\Models\Espaco;
use App\Models\Reserva;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Grava espaços com horários e bloqueios para a tela e para o assistente. Quem chama confere a autorização.
 */
final class GravadorDeEspaco
{
    /**
     * Cria o espaço do dono, com horários e bloqueios, em uma transação.
     *
     * @param  array<string, mixed>  $dados  já validados, com `horarios` e `bloqueios`
     */
    public function criar(User $dono, array $dados): Espaco
    {
        return DB::transaction(function () use ($dono, $dados): Espaco {
            $espaco = $dono->espacos()->create($this->atributos($dados));

            $this->gravarHorariosEBloqueios($espaco, $dados);

            return $espaco;
        });
    }

    /**
     * Atualiza o espaço. Horários e bloqueios enviados substituem os anteriores.
     *
     * @param  array<string, mixed>  $dados  já validados e completos (estado final)
     */
    public function atualizar(Espaco $espaco, array $dados): Espaco
    {
        return DB::transaction(function () use ($espaco, $dados): Espaco {
            $espaco->fill($this->atributos($dados))->save();

            $espaco->horarios()->delete();
            $espaco->bloqueios()->delete();

            $this->gravarHorariosEBloqueios($espaco, $dados);

            // Horário e bloqueio também mudam o cadastro: a proposta do assistente confere a data de atualização.
            $espaco->touch();

            return $espaco;
        });
    }

    /**
     * Exclui o espaço, mas só sem reservas. A FK de reservas é restrita, então a checagem vem antes.
     *
     * @throws ValidationException com a mensagem de `motivoParaNaoExcluir`
     */
    public function excluir(Espaco $espaco): void
    {
        $motivo = $this->motivoParaNaoExcluir($espaco);

        if ($motivo !== null) {
            throw ValidationException::withMessages(['espaco' => $motivo]);
        }

        $espaco->delete();
    }

    /**
     * Mensagem que impede a exclusão, ou null quando o espaço não tem reservas.
     */
    public function motivoParaNaoExcluir(Espaco $espaco): ?string
    {
        $reservas = Reserva::query()->where('espaco_id', $espaco->id);
        $hoje = now(config('agendamentos.fuso'))->toDateString();

        if ((clone $reservas)->where('data', '>=', $hoje)->exists()) {
            return 'Há reservas futuras usando este espaço. Desative em vez de excluir.';
        }

        if ($reservas->exists()) {
            return 'Há reservas usando este espaço. Desative em vez de excluir.';
        }

        return null;
    }

    /**
     * Atributos da tabela `espacos`, sem as listas de horários e bloqueios.
     *
     * @param  array<string, mixed>  $dados
     * @return array<string, mixed>
     */
    private function atributos(array $dados): array
    {
        return Arr::except($dados, ['horarios', 'bloqueios']);
    }

    /**
     * @param  array<string, mixed>  $dados
     */
    private function gravarHorariosEBloqueios(Espaco $espaco, array $dados): void
    {
        $espaco->horarios()->createMany($dados['horarios'] ?? []);
        $espaco->bloqueios()->createMany($dados['bloqueios'] ?? []);
    }
}
