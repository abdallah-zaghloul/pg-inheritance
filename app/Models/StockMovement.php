<?php

namespace App\Models;

use Arr;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

#[Fillable(['amount'])]
class StockMovement extends Model
{
    public $timestamps = false;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['amount' => 'integer'];
    }

    public function details(): MorphTo
    {
        $relation = $this->morphTo(__FUNCTION__, 'source', 'id');

        static $constraints = Arr::map([
            Transfer::class => ['id', 'type'],
            Waste::class => ['id', 'cause'],
        ], fn (array $columns) => fn (Builder $query) => $query
            ->select($columns)
            ->afterQuery(fn (Collection $models) => $models->makeHidden('id')),
        );

        @$constraints[$relation->getRelated()::class]?->__invoke($relation->getQuery());

        return $relation->constrain($constraints);
    }
}
