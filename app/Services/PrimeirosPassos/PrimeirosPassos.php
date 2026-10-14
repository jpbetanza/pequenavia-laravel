<?php

namespace App\Services\PrimeirosPassos;

use App\Models\Equipamento;
use App\Models\Espaco;
use App\Models\Grupo;
use App\Models\InscricaoPush;
use App\Models\Solicitacao;
use App\Models\User;
use App\Services\Push\Notificador;
use Carbon\CarbonImmutable;

/**
 * Roteiro de primeiros passos de quem começa a usar a agenda: espaços, equipamentos, grupos, o link do formulário e
 * os avisos no celular. O progresso vem dos dados (quem já tem cadastros vê os passos feitos); o que só a pessoa
 * sabe (pulos, "já enviei o link", adiar, dispensar) fica em `users.primeiros_passos`.
 */
final class PrimeirosPassos
{
    /** @var array<string, array{titulo: string, descricao: string}> */
    private const PASSOS = [
        'espacos' => ['titulo' => 'Espaços', 'descricao' => 'Salas, capacidade e horário de funcionamento'],
        'equipamentos' => ['titulo' => 'Equipamentos', 'descricao' => 'Projetores, caixas de som, cadeiras extras'],
        'grupos' => ['titulo' => 'Grupos e pastorais', 'descricao' => 'Quem usa os espaços, com a coordenação'],
        'formulario' => ['titulo' => 'Formulário de pedidos', 'descricao' => 'O link para os grupos pedirem reservas'],
        'avisos' => ['titulo' => 'Avisos no celular', 'descricao' => 'Saiba na hora quando chegar um pedido'],
    ];

    public const ACOES = ['pular', 'adiar', 'dispensar', 'link_enviado', 'modal_visto'];

    /**
     * Estado do roteiro para a tela, ou null quando não deve aparecer: dispensado, ou tudo feito sem nunca ter começado
     * (quem já chegou com a agenda pronta não vê boas-vindas). Só lê: o começo é gravado pelo Início (registrarInicio).
     *
     * @return array{fase: 'inicio'|'andamento'|'concluido', feitos: int, total: int, atual: string|null, adiado: bool, mostrar_modal: bool, passos: list<array{chave: string, titulo: string, descricao: string, feito: bool, pulado: bool}>, contagens: array{espacos: int, equipamentos: int, grupos: int}, link_do_formulario: string|null}|null
     */
    public function estado(User $usuario): ?array
    {
        $guardado = $usuario->primeiros_passos ?? [];

        if (isset($guardado['dispensado_em'])) {
            return null;
        }

        $contagens = [
            'espacos' => Espaco::query()->where('user_id', $usuario->id)->count(),
            'equipamentos' => Equipamento::query()->where('user_id', $usuario->id)->count(),
            'grupos' => Grupo::query()->where('user_id', $usuario->id)->count(),
        ];
        $feitos = [
            'espacos' => $contagens['espacos'] > 0,
            'equipamentos' => $contagens['equipamentos'] > 0,
            'grupos' => $contagens['grupos'] > 0,
            'formulario' => isset($guardado['link_enviado_em'])
                || Solicitacao::query()->where('user_id', $usuario->id)->exists(),
            'avisos' => InscricaoPush::query()->where('user_id', $usuario->id)->exists(),
        ];
        $pulados = $guardado['pulados'] ?? [];

        $passos = [];

        foreach ($this->chaves() as $chave) {
            $passos[] = [
                'chave' => $chave,
                ...self::PASSOS[$chave],
                'feito' => $feitos[$chave],
                'pulado' => ! $feitos[$chave] && in_array($chave, $pulados, true),
            ];
        }

        $resolvidos = count(array_filter($passos, fn (array $passo) => $passo['feito'] || $passo['pulado']));
        $total = count($passos);
        $concluido = $resolvidos === $total;

        if ($concluido && ! isset($guardado['iniciado_em'])) {
            return null;
        }

        $atual = collect($passos)->first(fn (array $passo) => ! $passo['feito'] && ! $passo['pulado']);

        return [
            'fase' => match (true) {
                $concluido => 'concluido',
                $resolvidos === 0 => 'inicio',
                default => 'andamento',
            },
            'feitos' => $resolvidos,
            'total' => $total,
            'atual' => $atual['chave'] ?? null,
            'adiado' => isset($guardado['adiado_ate']) && CarbonImmutable::parse($guardado['adiado_ate'])->isFuture(),
            // O modal de boas-vindas aparece uma vez, no primeiro acesso, antes de qualquer passo resolvido.
            'mostrar_modal' => $resolvidos === 0 && ! isset($guardado['modal_visto_em']),
            'passos' => $passos,
            'contagens' => $contagens,
            // O endereço só existe depois que a agenda ganha o slug (o Início garante isso).
            'link_do_formulario' => $usuario->agenda_slug === null ? null : route('reservar.create', $usuario->agenda_slug),
        ];
    }

    /**
     * Grava o começo do roteiro quando o Início aparece com passos pendentes, e garante o endereço do formulário.
     * A partir daí, ao terminar, a pessoa vê o "Tudo pronto".
     */
    public function registrarInicio(User $usuario): void
    {
        $usuario->agendaSlug();
        $guardado = $usuario->primeiros_passos ?? [];

        if (isset($guardado['iniciado_em']) || isset($guardado['dispensado_em'])) {
            return;
        }

        $estado = $this->estado($usuario);

        if ($estado !== null && $estado['fase'] !== 'concluido') {
            $this->guardar($usuario, ['iniciado_em' => now()->toIso8601String()]);
        }
    }

    /**
     * Aplica uma escolha da pessoa no roteiro.
     *
     * @param  'pular'|'adiar'|'dispensar'|'link_enviado'|'modal_visto'  $acao
     */
    public function aplicar(User $usuario, string $acao, ?string $passo = null): void
    {
        $guardado = $usuario->primeiros_passos ?? [];

        $mudanca = match ($acao) {
            'pular' => ['pulados' => array_values(array_unique([...($guardado['pulados'] ?? []), (string) $passo]))],
            'adiar' => ['adiado_ate' => now()->addDay()->toIso8601String()],
            'dispensar' => ['dispensado_em' => now()->toIso8601String()],
            'link_enviado' => ['link_enviado_em' => now()->toIso8601String()],
            'modal_visto' => ['modal_visto_em' => now()->toIso8601String()],
        };

        $this->guardar($usuario, $mudanca);
    }

    /**
     * Chaves dos passos que valem neste servidor. Sem as chaves VAPID não há como ativar avisos: o passo sai.
     *
     * @return list<string>
     */
    public function chaves(): array
    {
        $chaves = array_keys(self::PASSOS);

        return Notificador::configurado() ? $chaves : array_values(array_diff($chaves, ['avisos']));
    }

    /**
     * @param  array<string, mixed>  $mudanca
     */
    private function guardar(User $usuario, array $mudanca): void
    {
        $usuario->forceFill(['primeiros_passos' => [...($usuario->primeiros_passos ?? []), ...$mudanca]])->save();
    }
}
