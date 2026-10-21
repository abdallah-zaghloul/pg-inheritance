<?php

namespace App\Models;

use Database\Factories\TransferFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;

#[Fillable(['amount', 'type'])]
#[Hidden(['source'])]
class Transfer extends StockMovement
{
    /** @use HasFactory<TransferFactory> */
    use HasFactory;
}
