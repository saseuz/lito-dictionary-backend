<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dictionary_results', function (Blueprint $table): void {
            $table->id();
            $table->string('provider', 64);
            $table->string('language', 32);
            $table->string('lookup', 255);
            $table->string('lookup_hash', 64);
            $table->enum('status', ['hit', 'miss', 'error']);
            $table->json('payload')->nullable();
            $table->text('attribution')->nullable();
            $table->text('terms_url')->nullable();
            $table->timestamp('fetched_at');
            $table->timestamp('expires_at')->nullable();
            $table->string('response_hash', 64)->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();
            $table->unique(['provider', 'language', 'lookup_hash'], 'dictionary_results_lookup');
            $table->index(['provider', 'status', 'expires_at'], 'dictionary_results_expiry');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dictionary_results');
    }
};
