<?php

namespace App\Services\Gestao;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Quais contas entram no painel. Por padrão só os clientes (admin = false); o gestor pode ligar, na sessão,
 * a inclusão das contas de gestão para conferir as próprias conversas de teste.
 */
class EscopoDaGestao
{
    public const CHAVE_DA_SESSAO = 'gestao.incluir_gestores';

    public function incluiGestores(): bool
    {
        return (bool) session()->get(self::CHAVE_DA_SESSAO, false);
    }

    public function definir(bool $incluir): void
    {
        session()->put(self::CHAVE_DA_SESSAO, $incluir);
    }

    /**
     * Consulta das contas do painel.
     */
    public function usuarios(): Builder
    {
        return DB::table('users')->when(! $this->incluiGestores(), fn (Builder $consulta) => $consulta->where('admin', false));
    }

    /**
     * Subconsulta com os ids das contas do painel, para usar em whereIn.
     */
    public function ids(): Builder
    {
        return $this->usuarios()->select('id');
    }
}
