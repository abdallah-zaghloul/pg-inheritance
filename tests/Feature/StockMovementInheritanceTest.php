<?php

use App\Models\StockMovement;
use App\Models\Transfer;
use App\Models\Waste;
use Database\Seeders\StockMovementSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Ramsey\Uuid\Uuid;

beforeEach(function (): void {
    if (DB::connection()->getDriverName() !== 'pgsql') {
        $this->markTestSkipped('Run with a PostgreSQL test connection to verify table inheritance.');
    }

    DB::beginTransaction();
    $this->stockMovementTransaction = true;
    $schema = 'stock_movement_test_'.bin2hex(random_bytes(8));
    DB::statement('CREATE SCHEMA "'.$schema.'"');
    DB::statement('SET LOCAL search_path TO "'.$schema.'"');

    $this->stockMovementMigration = require database_path('migrations/2026_09_17_120546_create_stock_movement_inheritance_tables.php');
    $this->stockMovementMigration->up();
});

afterEach(function (): void {
    if ($this->stockMovementTransaction ?? false) {
        DB::rollBack();
    }
});

it('stores child records with UUIDv7 keys and reads both through the empty parent', function (): void {
    $transfer = Transfer::factory()->create(['amount' => 100, 'type' => 'in']);
    $waste = Waste::factory()->create(['amount' => 5, 'cause' => 'Damaged packaging']);

    expect(Uuid::fromString($transfer->id)->getVersion())->toBe(7);
    expect(Uuid::fromString($waste->id)->getVersion())->toBe(7);
    expect(StockMovement::query()->pluck('id')->all())->toEqualCanonicalizing([$transfer->id, $waste->id]);
    expect((int) DB::selectOne('SELECT count(*) AS total FROM ONLY stock_movements')->total)->toBe(0);
    expect($transfer->fresh()->amount)->toBe(100);
    expect($waste->fresh()->cause)->toBe('Damaged packaging');
    expect($transfer->fresh()->source)->toBe('transfers');
    expect($waste->fresh()->source)->toBe('wastes');
    expect(StockMovement::query()->whereKey($transfer->id)->value('source'))->toBe('transfers');
    $this->assertDatabaseCount('transfers', 1);
    $this->assertDatabaseCount('wastes', 1);
});

it('omits duplicated attributes from details without mixing children that share an id', function (): void {
    $transfer = Transfer::factory()->create(['amount' => 100, 'type' => 'in']);
    Waste::factory()->create(['id' => $transfer->id, 'amount' => 5, 'cause' => 'Damaged']);

    $movements = StockMovement::with('details')->orderBy('source')->get()->toArray();

    expect($movements)->toBe([
        ['id' => $transfer->id, 'amount' => 100, 'source' => 'transfers', 'details' => ['type' => 'in']],
        ['id' => $transfer->id, 'amount' => 5, 'source' => 'wastes', 'details' => ['cause' => 'Damaged']],
    ]);
    expect($transfer->fresh()->toArray())->toBe([
        'id' => $transfer->id, 'amount' => 100, 'source' => 'transfers', 'type' => 'in',
    ]);
});

it('rejects direct parent inserts through Eloquent and SQL', function (string $method): void {
    expect(fn () => DB::transaction(function () use ($method): void {
        if ($method === 'eloquent') {
            StockMovement::query()->create(['amount' => 10]);
        } else {
            DB::table('stock_movements')->insert(['id' => (string) Str::uuid7(), 'amount' => 10]);
        }
    }))->toThrow(QueryException::class);

    $this->assertDatabaseCount('stock_movements', 0);
})->with(['eloquent', 'sql']);

it('rejects invalid transfer types', function (): void {
    expect(fn () => DB::transaction(fn () => Transfer::factory()->create(['type' => 'invalid'])))
        ->toThrow(QueryException::class);

    $this->assertDatabaseCount('transfers', 0);
});

it('requires inherited amounts and child causes', function (string $model, array $attributes): void {
    expect(fn () => DB::transaction(fn () => $model::factory()->create($attributes)))
        ->toThrow(QueryException::class);

    $this->assertDatabaseCount('stock_movements', 0);
})->with([
    'transfer amount' => [Transfer::class, ['amount' => null]],
    'waste amount' => [Waste::class, ['amount' => null]],
    'waste cause' => [Waste::class, ['cause' => null]],
]);

it('enforces primary keys on each child', function (string $model): void {
    $record = $model::factory()->create();

    expect(fn () => DB::transaction(fn () => $model::factory()->create(['id' => $record->id])))
        ->toThrow(QueryException::class);

    $this->assertDatabaseCount($record->getTable(), 1);
})->with([
    'transfers' => [Transfer::class],
    'wastes' => [Waste::class],
]);

it('updates and deletes children through their own models', function (): void {
    $transfer = Transfer::factory()->create(['type' => 'out']);
    $waste = Waste::factory()->create();

    $transfer->update(['amount' => 42]);
    $waste->delete();

    expect($transfer->fresh()->amount)->toBe(42);
    $this->assertModelMissing($waste);
    expect(StockMovement::query()->pluck('id')->all())->toBe([$transfer->id]);
});

it('seeds child tables while leaving the physical parent empty', function (): void {
    $this->seed(StockMovementSeeder::class);

    $this->assertDatabaseHas('transfers', ['amount' => 100, 'type' => 'in']);
    $this->assertDatabaseHas('transfers', ['amount' => 25, 'type' => 'out']);
    $this->assertDatabaseHas('wastes', ['amount' => 5, 'cause' => 'Damaged packaging']);
    $this->assertDatabaseCount('stock_movements', 3);
    expect((int) DB::selectOne('SELECT count(*) AS total FROM ONLY stock_movements')->total)->toBe(0);
});

it('rolls back children before the parent and can migrate again', function (): void {
    $this->stockMovementMigration->down();

    expect(DB::selectOne("SELECT to_regclass('stock_movements') AS name")->name)->toBeNull();
    expect(DB::selectOne("SELECT to_regclass('transfers') AS name")->name)->toBeNull();
    expect(DB::selectOne("SELECT to_regclass('wastes') AS name")->name)->toBeNull();

    $this->stockMovementMigration->up();
    $this->assertModelExists(Transfer::factory()->create());
});
