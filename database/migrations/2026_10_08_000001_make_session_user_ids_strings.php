<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('sessions', function (Blueprint $table) {
            // Keep the existing index; account IDs may be integers or UUIDs.
            $table->string('user_id')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        throw new RuntimeException(
            'Cannot safely convert sessions.user_id back to integers: UUID account IDs may exist. Keep the string column or plan an explicit data migration.',
        );
    }
};
