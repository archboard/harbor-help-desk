<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Serves the `custom_fields @> ...` ticket filters. A GIN index can't lead with `workspace_id`
 * without the `btree_gin` extension, so this is an exception to tenant indexes starting with
 * it. Searches still filter by workspace through the tenant scope.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('create index tickets_custom_fields_gin on tickets using gin (custom_fields jsonb_path_ops)');
    }

    public function down(): void
    {
        DB::statement('drop index tickets_custom_fields_gin');
    }
};
