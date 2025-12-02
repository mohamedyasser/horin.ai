<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Add tsvector column for full-text search
        DB::statement('ALTER TABLE assets ADD COLUMN IF NOT EXISTS fts tsvector');

        // Populate existing data with weighted search
        // Using 'simple' config for language-agnostic matching (works with Arabic text)
        DB::statement("
            UPDATE assets SET fts =
                setweight(to_tsvector('simple', coalesce(symbol, '')), 'A') ||
                setweight(to_tsvector('simple', coalesce(name_en, '')), 'B') ||
                setweight(to_tsvector('simple', coalesce(name_ar, '')), 'B') ||
                setweight(to_tsvector('simple', coalesce(description_en, '')), 'C') ||
                setweight(to_tsvector('simple', coalesce(description_ar, '')), 'C')
        ");

        // Create GIN index for fast full-text search
        DB::statement('CREATE INDEX IF NOT EXISTS assets_fts_idx ON assets USING GIN(fts)');

        // Create trigger function for auto-update on insert/update
        DB::statement("
            CREATE OR REPLACE FUNCTION assets_fts_trigger() RETURNS trigger AS \$\$
            BEGIN
                NEW.fts :=
                    setweight(to_tsvector('simple', coalesce(NEW.symbol, '')), 'A') ||
                    setweight(to_tsvector('simple', coalesce(NEW.name_en, '')), 'B') ||
                    setweight(to_tsvector('simple', coalesce(NEW.name_ar, '')), 'B') ||
                    setweight(to_tsvector('simple', coalesce(NEW.description_en, '')), 'C') ||
                    setweight(to_tsvector('simple', coalesce(NEW.description_ar, '')), 'C');
                RETURN NEW;
            END
            \$\$ LANGUAGE plpgsql
        ");

        // Create trigger
        DB::statement('DROP TRIGGER IF EXISTS assets_fts_update ON assets');
        DB::statement('
            CREATE TRIGGER assets_fts_update
            BEFORE INSERT OR UPDATE ON assets
            FOR EACH ROW EXECUTE FUNCTION assets_fts_trigger()
        ');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('DROP TRIGGER IF EXISTS assets_fts_update ON assets');
        DB::statement('DROP FUNCTION IF EXISTS assets_fts_trigger()');
        DB::statement('DROP INDEX IF EXISTS assets_fts_idx');
        DB::statement('ALTER TABLE assets DROP COLUMN IF EXISTS fts');
    }
};
