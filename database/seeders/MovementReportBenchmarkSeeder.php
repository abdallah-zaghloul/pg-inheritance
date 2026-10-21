<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class MovementReportBenchmarkSeeder extends Seeder
{
    public function run(): void
    {
        $tables = ['stock_movements', 'inventory_movements'];
        $queries = [
            'Full aggregate' => 'SELECT source, SUM(amount) FROM %s GROUP BY source ORDER BY source',
            'Waste aggregate' => "SELECT SUM(amount), COUNT(*) FROM %s WHERE source = 'wastes'",
            'ID range aggregate' => 'SELECT source, SUM(amount), COUNT(*) FROM %s WHERE id BETWEEN 55001 AND 65000 GROUP BY source ORDER BY source',
            'First 100 transfers' => "SELECT id, amount, source FROM %s WHERE source = 'transfers' ORDER BY id LIMIT 100",
        ];
        $rows = [];

        // SET LOCAL settings are restored when each transaction finishes.
        foreach (['Serial', 'Parallel encouraged'] as $mode) {
            DB::transaction(function () use ($mode, $tables, $queries, &$rows): void {
                DB::statement('SET LOCAL max_parallel_workers_per_gather = '.($mode === 'Serial' ? '0' : '2'));

                if ($mode !== 'Serial') {
                    DB::statement('SET LOCAL min_parallel_table_scan_size = 0');
                    DB::statement('SET LOCAL parallel_setup_cost = 0');
                    DB::statement('SET LOCAL parallel_tuple_cost = 0');
                }

                foreach ($queries as $name => $sql) {
                    if (DB::select(sprintf($sql, $tables[0])) != DB::select(sprintf($sql, $tables[1]))) {
                        throw new \RuntimeException("Report results differ: {$name}");
                    }

                    $samples = array_fill_keys($tables, []);
                    $workers = array_fill_keys($tables, []);

                    // Two warm-ups, then ten measurements; alternate execution order.
                    for ($run = 0; $run < 12; $run++) {
                        foreach ($run % 2 === 0 ? $tables : array_reverse($tables) as $table) {
                            $result = DB::select('EXPLAIN (ANALYZE, BUFFERS, FORMAT JSON) '.sprintf($sql, $table));
                            $plan = json_decode($result[0]->{'QUERY PLAN'}, true, flags: JSON_THROW_ON_ERROR)[0];

                            if ($run >= 2) {
                                $samples[$table][] = $plan['Execution Time'];
                                $workers[$table][] = $this->launchedWorkers($plan['Plan']);
                            }
                        }
                    }

                    foreach ($tables as $table) {
                        sort($samples[$table]);
                        $rows[] = [
                            $mode,
                            $name,
                            $table,
                            number_format(($samples[$table][4] + $samples[$table][5]) / 2, 3),
                            number_format(min($samples[$table]), 3),
                            number_format(max($samples[$table]), 3),
                            min($workers[$table]).'–'.max($workers[$table]),
                        ];
                    }
                }
            });
        }

        $headers = ['Mode', 'Report', 'Table', 'Median ms', 'Min ms', 'Max ms', 'Workers'];

        if ($this->command !== null) {
            $this->command->info('Matching results verified. Times exclude Laravel hydration; 10 measured runs after 2 warm-ups.');
            $this->command->table($headers, $rows);
        } else {
            dump(array_map(fn (array $row) => array_combine($headers, $row), $rows));
        }
    }

    private function launchedWorkers(array $plan): int
    {
        $workers = $plan['Workers Launched'] ?? 0;

        foreach ($plan['Plans'] ?? [] as $child) {
            $workers += $this->launchedWorkers($child);
        }

        return $workers;
    }
}
