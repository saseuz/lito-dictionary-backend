<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('permission_groups', function (Blueprint $table) {
            $table->id();
            $table->string('key');
            $table->string('name');
            $table->string('guard_name')->default('admin');
            $table->timestamps();
            $table->unique(['key', 'guard_name']);
        });
        Schema::table('permissions', function (Blueprint $table) {
            $table->foreignId('permission_group_id')->nullable()->constrained('permission_groups')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('permissions', fn (Blueprint $table) => $table->dropConstrainedForeignId('permission_group_id'));
        Schema::dropIfExists('permission_groups');
    }
};
