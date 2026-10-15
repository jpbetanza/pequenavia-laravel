<?php

namespace App\Services\Assistente;

use App\Enums\StatusSolicitacao;
use App\Models\Reserva;
use App\Models\Solicitacao;
use App\Models\User;
use App\Services\PrimeirosPassos\PrimeirosPassos;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * O que aparece numa conversa vazia: uma saudação, uma frase de abertura e até quatro sugestões, escolhidas pela situação.
 *
 * A ordem é a regra: o que espera a secretaria (pedidos, equipamentos a devolver), depois a agenda, depois uma ação.
 * Enquanto os primeiros passos não terminam, a configuração toma o lugar de tudo.
 */
final class SugestoesDoAssistente
{
    private const MAXIMO = 4;

    public function __construct(private readonly PrimeirosPassos $primeirosPassos) {}

    /**
     * @return array{situacao: 'primeiro'|'pendencias'|'tranquilo', saudacao: string, abertura: string, rotulo: string, sugestoes: list<array{texto: string, pergunta: string, destaque: bool}>}
     */
    public function para(User $usuario): array
    {
        $agora = CarbonImmutable::now(config('agendamentos.fuso'));
        $nome = Str::of($usuario->name)->trim()->before(' ')->toString();
        $roteiro = $this->primeirosPassos->estado($usuario);

        if ($roteiro !== null && $roteiro['fase'] !== 'concluido' && ! $roteiro['adiado']) {
            return $this->primeiroAcesso($nome, $roteiro['total'] - $roteiro['feitos']);
        }

        $saudacao = match (true) {
            $agora->hour < 12 => 'Bom dia',
            $agora->hour < 18 => 'Boa tarde',
            default => 'Boa noite',
        }.($nome === '' ? '.' : ", {$nome}.");

        $pedidos = Solicitacao::query()
            ->where('user_id', $usuario->id)
            ->where('status', StatusSolicitacao::Pendente->value)
            ->count();
        $aDevolver = $this->equipamentosADevolver($usuario, $agora);

        $sugestoes = [];

        if ($pedidos > 0) {
            $sugestoes[] = $this->sugestao(
                $pedidos === 1 ? 'Responder o pedido' : "Responder os {$pedidos} pedidos",
                'Quais solicitações estão esperando resposta? Quero responder.',
            );
        }

        if ($aDevolver !== []) {
            $sugestoes[] = $this->sugestao(
                count($aDevolver) === 1 ? "{$aDevolver[0]} a devolver" : 'Equipamentos a devolver',
                'Quais equipamentos foram entregues e ainda não voltaram?',
            );
        }

        $temHoje = $agora->hour < 18 && Reserva::query()
            ->where('user_id', $usuario->id)
            ->where('data', $agora->toDateString())
            ->where('fim', '>', $agora->format('H:i'))
            ->exists();

        $sugestoes[] = $temHoje
            ? $this->sugestao('O que tem hoje?', 'O que tem na agenda hoje?')
            : $this->sugestao('O que tem amanhã?', 'O que tem na agenda amanhã?');
        $sugestoes[] = $this->sugestao('Reservar um espaço', 'Quero reservar um espaço.');
        $sugestoes[] = $this->sugestao('Livre no sábado?', 'Quais espaços estão livres no próximo sábado?');
        $sugestoes[] = $this->sugestao('Certificados deste mês', 'Quantos certificados foram emitidos este mês?');

        $pendente = $pedidos > 0 || $aDevolver !== [];

        if ($pendente) {
            $sugestoes[0]['destaque'] = true;
        }

        return [
            'situacao' => $pendente ? 'pendencias' : 'tranquilo',
            'saudacao' => $saudacao,
            'abertura' => $pendente
                ? $this->resumoDasPendencias($pedidos, $aDevolver).' Posso cuidar disso com você.'
                : 'Nada pendente por aqui. Posso consultar a agenda, reservar ou responder pedidos.',
            'rotulo' => $pendente ? 'Para hoje' : 'Para começar',
            'sugestoes' => array_slice($sugestoes, 0, self::MAXIMO),
        ];
    }

    /**
     * @return array{situacao: 'primeiro', saudacao: string, abertura: string, rotulo: string, sugestoes: list<array{texto: string, pergunta: string, destaque: bool}>}
     */
    private function primeiroAcesso(string $nome, int $faltam): array
    {
        $continuar = $this->sugestao('Continuar a configuração', 'Vamos continuar a configuração da agenda.');
        $continuar['destaque'] = true;

        return [
            'situacao' => 'primeiro',
            'saudacao' => $nome === '' ? 'Boas-vindas.' : "Boas-vindas, {$nome}.",
            'abertura' => 'Vamos deixar a agenda pronta? '
                .($faltam === 1 ? 'Falta 1 passo.' : "Faltam {$faltam} passos.")
                .' Me mande uma planilha ou vá respondendo.',
            'rotulo' => 'Para configurar',
            'sugestoes' => [
                $continuar,
                $this->sugestao('Espaços por planilha', 'Quero cadastrar os espaços com uma planilha. Como faço?'),
                $this->sugestao('Link do formulário', 'Qual é o link do formulário de reservas para eu mandar aos grupos?'),
                $this->sugestao('O que você sabe fazer?', 'O que você sabe fazer aqui no PequenaVia?'),
            ],
        ];
    }

    /**
     * Nomes dos equipamentos que saíram numa reserva já começada e ainda não foram recolhidos.
     *
     * @return list<string>
     */
    private function equipamentosADevolver(User $usuario, CarbonImmutable $agora): array
    {
        return array_values(DB::table('reserva_equipamento')
            ->join('reservas', 'reservas.id', '=', 'reserva_equipamento.reserva_id')
            ->join('equipamentos', 'equipamentos.id', '=', 'reserva_equipamento.equipamento_id')
            ->where('reservas.user_id', $usuario->id)
            ->where('reservas.data', '<=', $agora->toDateString())
            ->whereNotNull('reserva_equipamento.entregue_em')
            ->whereNull('reserva_equipamento.recolhido_em')
            ->distinct()
            ->orderBy('equipamentos.nome')
            ->pluck('equipamentos.nome')
            ->map(fn ($nome) => (string) $nome)
            ->all());
    }

    /**
     * @param  list<string>  $aDevolver
     */
    private function resumoDasPendencias(int $pedidos, array $aDevolver): string
    {
        $partes = [];

        if ($pedidos > 0) {
            $partes[] = $pedidos === 1 ? '1 pedido espera resposta' : "{$pedidos} pedidos esperam resposta";
        }

        if ($aDevolver !== []) {
            $partes[] = count($aDevolver) === 1
                ? "o equipamento {$aDevolver[0]} ainda não voltou"
                : count($aDevolver).' equipamentos ainda não voltaram';
        }

        return Str::ucfirst(implode(' e ', $partes)).'.';
    }

    /**
     * @return array{texto: string, pergunta: string, destaque: bool}
     */
    private function sugestao(string $texto, string $pergunta): array
    {
        return ['texto' => $texto, 'pergunta' => $pergunta, 'destaque' => false];
    }
}
