<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class InventoryMovementsSeeder extends Seeder
{
    public function run(): void
    {
        // Copy the same IDs and values in the same source/id insertion order.
        DB::statement(<<<'SQL'
            INSERT INTO inventory_movements (id, amount, source)
            SELECT id, amount, source FROM stock_movements
            ORDER BY source, id
            ON CONFLICT (id) DO UPDATE
            SET amount = EXCLUDED.amount, source = EXCLUDED.source
            SQL);

        DB::selectOne(<<<'SQL'
            SELECT setval(pg_get_serial_sequence('inventory_movements', 'id'),
                COALESCE((SELECT MAX(id) FROM inventory_movements), 1),
                EXISTS (SELECT 1 FROM inventory_movements))
            SQL);

        DB::statement('ANALYZE inventory_movements');
    }
}
