<?php

namespace App\Services\Assistente\Ferramentas;

use App\Models\Conversa;
use App\Models\GeracaoCertificado;
use App\Models\User;
use Carbon\CarbonImmutable;

/**
 * Conta os certificados emitidos pelo usuário no período, por modelo. Os dias são lidos no fuso da agenda.
 */
final class ResumoDeCertificados implements Ferramenta
{
    use ValidaArgumentos;

    private const DIAS_MAXIMOS = 366;

    private const LIMITE_DE_ULTIMAS = 5;

    public function nome(): string
    {
        return 'resumo_de_certificados';
    }

    public function descricao(): string
    {
        return 'Conta os certificados emitidos pelo usuário em um período de no máximo 366 dias, por modelo, com as emissões mais recentes.';
    }

    public function parametros(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'inicio' => ['type' => 'string', 'description' => 'Primeiro dia do período, no formato AAAA-MM-DD.'],
                'fim' => ['type' => 'string', 'description' => 'Último dia do período, no formato AAAA-MM-DD.'],
                'modelo' => ['type' => 'string', 'description' => 'Nome do modelo de certificado, para filtrar. Opcional.'],
            ],
            'required' => ['inicio', 'fim'],
        ];
    }

    public function status(): string
    {
        return 'Consultando os certificados…';
    }

    public function executar(User $usuario, Conversa $conversa, array $argumentos): ResultadoDeFerramenta
    {
        $dados = $this->validar($argumentos, [
            'inicio' => ['required', 'date_format:Y-m-d'],
            'fim' => ['required', 'date_format:Y-m-d', 'after_or_equal:inicio'],
            'modelo' => ['nullable', 'string'],
        ]);

        $fuso = config('agendamentos.fuso');
        $inicio = CarbonImmutable::createFromFormat('Y-m-d', $dados['inicio'], $fuso)->startOfDay();
        $fim = CarbonImmutable::createFromFormat('Y-m-d', $dados['fim'], $fuso)->endOfDay();

        if ((int) $inicio->diffInDays($fim) + 1 > self::DIAS_MAXIMOS) {
            throw new ArgumentoInvalido('O período pode ter no máximo '.self::DIAS_MAXIMOS.' dias.');
        }

        $consulta = GeracaoCertificado::query()
            ->where('user_id', $usuario->id)
            ->whereBetween('created_at', [$inicio->utc(), $fim->utc()])
            ->when(isset($dados['modelo']), fn ($builder) => $builder->whereRaw('lower(modelo_title) = ?', [mb_strtolower($dados['modelo'])]));

        $total = (clone $consulta)->count();

        $porModelo = (clone $consulta)
            ->selectRaw('modelo_title, count(*) as total')
            ->groupBy('modelo_title')
            ->orderByDesc('total')
            ->orderBy('modelo_title')
            ->get()
            ->map(fn (GeracaoCertificado $linha) => ['modelo' => $linha->modelo_title, 'total' => (int) $linha->getAttribute('total')])
            ->values()
            ->all();

        $ultimas = (clone $consulta)
            ->orderByDesc('created_at')
            ->limit(self::LIMITE_DE_ULTIMAS)
            ->get()
            ->map(fn (GeracaoCertificado $geracao) => [
                'modelo' => $geracao->modelo_title,
                'quando' => $geracao->created_at->setTimezone($fuso)->toIso8601String(),
            ])
            ->values()
            ->all();

        return new ResultadoDeFerramenta(
            paraOModelo: ['total' => $total, 'por_modelo' => $porModelo, 'ultimas' => $ultimas],
            cartao: [
                'tipo' => 'certificados',
                'dados' => [
                    'inicio' => $dados['inicio'],
                    'fim' => $dados['fim'],
                    'total' => $total,
                    'por_modelo' => $porModelo,
                    'ultimas' => $ultimas,
                ],
            ],
        );
    }
}
