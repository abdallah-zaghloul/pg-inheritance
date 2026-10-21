<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            throw new RuntimeException('Stock movement inheritance requires PostgreSQL.');
        }

        DB::unprepared(<<<'SQL'
            -- Treat table names as fixed: renames do not refresh stored source values.
            CREATE OR REPLACE FUNCTION relation_source(relation_oid OID)
            RETURNS TEXT
            LANGUAGE plpgsql
            IMMUTABLE STRICT
            AS $$
            BEGIN
                RETURN regexp_replace(relation_oid::regclass::text, '^.*\.', '');
            END;
            $$;

            CREATE TABLE stock_movements (
                id BIGSERIAL NOT NULL,
                amount INTEGER NOT NULL,
                source TEXT GENERATED ALWAYS AS (relation_source(tableoid)) STORED,
                CONSTRAINT stock_movements_no_rows CHECK (false) NO INHERIT
            );

            REVOKE INSERT ON TABLE stock_movements FROM PUBLIC, CURRENT_USER;

            CREATE TABLE transfers (
                type TEXT NOT NULL CHECK (type IN ('in', 'out')),
                PRIMARY KEY (id)
            ) INHERITS (stock_movements);

            CREATE TABLE wastes (
                cause TEXT NOT NULL,
                PRIMARY KEY (id)
            ) INHERITS (stock_movements);
            SQL);

        foreach (['transfers', 'wastes'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->index(['source', 'id']);
            });
        }
    }

    public function down(): void
    {
        foreach (['transfers', 'wastes'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropIndex(['source', 'id']);
            });
        }

        DB::unprepared(<<<'SQL'
            DROP TABLE IF EXISTS wastes;
            DROP TABLE IF EXISTS transfers;
            DROP TABLE IF EXISTS stock_movements;
            DROP FUNCTION IF EXISTS relation_source(OID);
            SQL);

    }
};
