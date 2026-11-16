<?php

namespace App\Models\Concerns;

use App\Models\Account;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;
use LogicException;

/**
 * Isolamento entre organizadores (tenants).
 *
 * - Global scope "account": com um usuário de conta autenticado, toda consulta
 *   é filtrada por account_id = conta do usuário. Usuário autenticado sem conta
 *   que não seja admin não enxerga nada (nega por padrão).
 * - Sem usuário autenticado (fila, cron, rotas por token do convidado/telão),
 *   não há filtro: nesses fluxos o tenant vem do recurso resolvido pelo token.
 * - Na criação, account_id é preenchido com a conta do usuário autenticado.
 *   account_id nunca é mass-assignable: não pode vir do request.
 *
 * Models que aceitam registros "do sistema" (account_id nulo, ex.: molduras
 * prontas) definem `protected static bool $includeSystemRecords = true;`.
 */
trait BelongsToAccount
{
    public static function bootBelongsToAccount(): void
    {
        static::addGlobalScope('account', function (Builder $builder) {
            $user = Auth::user();

            if (! $user instanceof User) {
                return;
            }

            $column = $builder->getModel()->qualifyColumn('account_id');

            if ($user->account_id === null) {
                if (! $user->isAdmin()) {
                    $builder->whereRaw('1 = 0');
                }

                return;
            }

            if (static::includesSystemRecords()) {
                $builder->where(function (Builder $query) use ($column, $user) {
                    $query->where($column, $user->account_id)->orWhereNull($column);
                });

                return;
            }

            $builder->where($column, $user->account_id);
        });

        static::creating(function ($model) {
            $user = Auth::user();

            if (! $user instanceof User || $user->account_id === null) {
                return;
            }

            if ($model->account_id !== null && (int) $model->account_id !== (int) $user->account_id) {
                throw new LogicException('Tentativa de gravar registro em outra conta.');
            }

            $model->account_id = $user->account_id;
        });
    }

    public static function includesSystemRecords(): bool
    {
        return property_exists(static::class, 'includeSystemRecords') && static::$includeSystemRecords;
    }

    public function initializeBelongsToAccount(): void
    {
        // Defesa extra: mesmo que alguém adicione account_id ao $fillable, removemos aqui.
        $this->fillable = array_values(array_diff($this->fillable, ['account_id']));
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }
}
