<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (config('permission.teams')) {
            throw new RuntimeException('The single-role constraint requires permission.teams to be disabled.');
        }

        $tableName = config('permission.table_names.model_has_roles');
        $modelKey = config('permission.column_names.model_morph_key');

        $duplicate = DB::table($tableName)
            ->select('model_type', $modelKey)
            ->groupBy('model_type', $modelKey)
            ->havingRaw('COUNT(*) > 1')
            ->first();

        if ($duplicate !== null) {
            throw new RuntimeException(
                "Cannot add the single-role constraint: multiple role assignments exist for {$duplicate->model_type} ({$duplicate->{$modelKey}}). Resolve duplicates explicitly before retrying; no assignments were removed.",
            );
        }

        Schema::table($tableName, function (Blueprint $table) use ($modelKey): void {
            $table->unique(['model_type', $modelKey], 'model_has_roles_one_role_per_model_unique');
        });
    }

    public function down(): void
    {
        Schema::table(config('permission.table_names.model_has_roles'), function (Blueprint $table): void {
            $table->dropUnique('model_has_roles_one_role_per_model_unique');
        });
    }
};
