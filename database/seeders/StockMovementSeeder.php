<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class StockMovementSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            // Insert transfers before wastes, with deterministic sequential integer IDs.
            // InventoryMovementsSeeder uses the same source/id order.
            DB::statement(<<<'SQL'
                INSERT INTO transfers (id, amount, type)
                SELECT n,
                       1 + (n % 100),
                       CASE WHEN n % 2 = 0 THEN 'in' ELSE 'out' END
                FROM generate_series(1, 60000) AS series(n)
                ORDER BY 1
                ON CONFLICT (id) DO NOTHING
                SQL);

            DB::statement(<<<'SQL'
                INSERT INTO wastes (id, amount, cause)
                SELECT 60000 + n,
                       1 + (n % 25),
                       (ARRAY['Damaged packaging', 'Expired', 'Spillage', 'Quality issue'])[1 + (n % 4)]
                FROM generate_series(1, 40000) AS series(n)
                ORDER BY 1
                ON CONFLICT (id) DO NOTHING
                SQL);
            // Explicit seed IDs must advance the shared auto-increment sequence.
            DB::selectOne(<<<'SQL'
                SELECT setval(pg_get_serial_sequence('stock_movements', 'id'),
                    (SELECT MAX(id) FROM stock_movements), true)
                SQL);
        });

        foreach (['transfers', 'wastes', 'stock_movements'] as $table) {
            DB::statement("ANALYZE {$table}");
        }
    }
}
