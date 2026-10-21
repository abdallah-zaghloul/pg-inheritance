<?php

namespace App\Models;

use Database\Factories\WasteFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;

#[Fillable(['amount', 'cause'])]
#[Hidden(['source'])]
class Waste extends StockMovement
{
    /** @use HasFactory<WasteFactory> */
    use HasFactory;
}
