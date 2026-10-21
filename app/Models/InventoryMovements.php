<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

#[Fillable(['id', 'amount', 'source'])]
class InventoryMovements extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return ['amount' => 'integer'];
    }

    public function details(): MorphTo
    {
        return $this->morphTo(__FUNCTION__, 'source', 'id');
    }
}
