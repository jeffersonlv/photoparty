<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Sobe/desce um registro numa lista ordenada por sort_order, renumerando 0..n.
 */
class Reorder
{
    /**
     * @param  Collection<int, Model>  $ordered  lista já ordenada
     */
    public static function move(Collection $ordered, Model $model, string $direction): void
    {
        $list = $ordered->values()->all();
        $index = collect($list)->search(fn (Model $m) => $m->is($model));

        if ($index === false) {
            return;
        }

        $target = $direction === 'up' ? $index - 1 : $index + 1;

        if (isset($list[$target])) {
            [$list[$index], $list[$target]] = [$list[$target], $list[$index]];
        }

        foreach ($list as $position => $item) {
            if ((int) $item->getAttribute('sort_order') !== $position) {
                $item->newQueryWithoutScopes()->whereKey($item->getKey())->update(['sort_order' => $position]);
            }
        }
    }
}
