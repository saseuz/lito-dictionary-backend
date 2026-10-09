<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dictionary_languages', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 32)->unique();
            $table->string('name', 128);
            $table->timestamps();
        });
        Schema::create('dictionary_parts_of_speech', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 64)->unique();
            $table->string('name', 128);
            $table->timestamps();
        });
        Schema::create('dictionary_sources', function (Blueprint $table): void {
            $table->id();
            $table->string('key', 64)->unique();
            $table->string('name');
            $table->text('homepage_url');
            $table->string('default_license_identifier');
            $table->text('default_license_url');
            $table->text('default_attribution_text');
            $table->unsignedBigInteger('active_release_id')->nullable()->index();
            $table->timestamps();
        });
        Schema::create('dictionary_releases', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('source_id')->constrained('dictionary_sources')->restrictOnDelete();
            $table->string('release_key', 64);
            $table->string('artifact_sha256', 64);
            $table->string('manifest_sha256', 64);
            $table->string('content_sha256', 64)->nullable();
            $table->string('adapter_version', 64);
            $table->string('scope_key', 64);
            $table->enum('artifact_kind', ['fixture', 'snapshot']);
            $table->text('source_url');
            $table->date('upstream_dump_date')->nullable();
            $table->timestamp('extracted_at')->nullable();
            $table->string('extractor_version');
            $table->string('license_identifier');
            $table->text('license_url');
            $table->text('attribution_text');
            $table->json('manifest');
            $table->enum('status', ['staging', 'ready', 'failed']);
            $table->timestamp('ready_at')->nullable();
            $table->timestamps();
            $table->unique(['source_id', 'release_key'], 'dict_release_identity');
            $table->index(['source_id', 'status'], 'dict_release_status');
        });
        Schema::table('dictionary_sources', function (Blueprint $table): void {
            $table->foreign('active_release_id')->references('id')->on('dictionary_releases')->restrictOnDelete();
        });
        Schema::create('dictionary_words', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('language_id')->constrained('dictionary_languages')->restrictOnDelete();
            $table->string('headword');
            $table->string('headword_hash', 64);
            $table->timestamps();
            $table->unique(['language_id', 'headword_hash'], 'dict_word_identity');
        });
        Schema::create('dictionary_lexemes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('word_id')->constrained('dictionary_words')->restrictOnDelete();
            $table->foreignId('release_id')->index()->constrained('dictionary_releases')->restrictOnDelete();
            $table->foreignId('part_of_speech_id')->index()->constrained('dictionary_parts_of_speech')->restrictOnDelete();
            $table->string('source_record_hash', 64);
            $table->unsignedInteger('source_line_number');
            $table->text('source_page_title');
            $table->text('source_page_url');
            $table->string('source_pos', 64)->nullable();
            $table->string('etymology_key')->nullable();
            $table->text('etymology_text')->nullable();
            $table->json('raw_payload');
            $table->timestamps();
            $table->unique(['release_id', 'source_record_hash'], 'dict_lexeme_identity');
            $table->index(['word_id', 'release_id'], 'dict_word_release');
        });
        Schema::create('dictionary_senses', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('lexeme_id')->constrained('dictionary_lexemes')->restrictOnDelete();
            $table->unsignedInteger('position');
            $table->json('metadata');
            $table->unique(['lexeme_id', 'position'], 'dict_sense_position');
            $table->unique(['id', 'lexeme_id'], 'dict_sense_scope');
        });
        Schema::create('dictionary_definitions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('sense_id')->constrained('dictionary_senses')->restrictOnDelete();
            $table->unsignedInteger('position');
            $table->text('text');
            $table->unique(['sense_id', 'position'], 'dict_definition_position');
        });
        Schema::create('dictionary_examples', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('sense_id')->constrained('dictionary_senses')->restrictOnDelete();
            $table->unsignedInteger('position');
            $table->text('text');
            $table->enum('kind', ['usage', 'quotation', 'unknown']);
            $table->text('source_reference')->nullable();
            $table->enum('publication_status', ['eligible', 'withheld']);
            $table->text('license_identifier')->nullable();
            $table->text('license_url')->nullable();
            $table->text('attribution_text')->nullable();
            $table->text('rights_note')->nullable();
            $table->json('metadata');
            $table->unique(['sense_id', 'position'], 'dict_example_position');
        });
        Schema::create('dictionary_pronunciations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('lexeme_id')->constrained('dictionary_lexemes')->restrictOnDelete();
            $table->string('source_path');
            $table->enum('notation', ['ipa', 'enpr']);
            $table->text('transcription');
            $table->string('form_text')->nullable();
            $table->json('metadata');
            $table->unique(['lexeme_id', 'source_path', 'notation'], 'dict_pronunciation_path');
        });
        Schema::create('dictionary_forms', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('lexeme_id')->constrained('dictionary_lexemes')->restrictOnDelete();
            $table->string('source_path');
            $table->string('form');
            $table->string('form_hash', 64)->index();
            $table->json('metadata');
            $table->unique(['lexeme_id', 'source_path'], 'dict_form_path');
        });
        Schema::create('dictionary_relations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('lexeme_id')->constrained('dictionary_lexemes')->restrictOnDelete();
            $table->unsignedBigInteger('sense_id')->nullable()->index();
            $table->string('source_path');
            $table->string('kind', 64);
            $table->foreignId('target_language_id')->constrained('dictionary_languages')->restrictOnDelete();
            $table->string('target_text');
            $table->string('target_hash', 64);
            $table->foreignId('target_word_id')->nullable()->index()->constrained('dictionary_words')->restrictOnDelete();
            $table->json('metadata');
            $table->unique(['lexeme_id', 'source_path'], 'dict_relation_path');
            $table->index(['target_language_id', 'target_hash'], 'dict_relation_target');
            $table->foreign(['sense_id', 'lexeme_id'], 'dict_relation_sense_scope')->references(['id', 'lexeme_id'])->on('dictionary_senses')->restrictOnDelete();
        });
        Schema::create('dictionary_import_runs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('release_id')->constrained('dictionary_releases')->restrictOnDelete();
            $table->enum('status', ['running', 'completed', 'failed']);
            $table->timestamp('started_at');
            $table->timestamp('finished_at')->nullable();
            $table->unsignedInteger('records_seen')->default(0);
            $table->unsignedInteger('records_inserted')->default(0);
            $table->unsignedInteger('records_unchanged')->default(0);
            $table->unsignedInteger('records_skipped')->default(0);
            $table->unsignedInteger('records_rejected')->default(0);
            $table->text('summary')->nullable();
            $table->timestamps();
            $table->index(['release_id', 'started_at'], 'dict_run_release');
        });
        Schema::create('dictionary_import_errors', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('import_run_id')->constrained('dictionary_import_runs')->restrictOnDelete();
            $table->unsignedInteger('line_number')->nullable();
            $table->string('source_record_hash', 64)->nullable();
            $table->string('code', 64);
            $table->text('message');
            $table->json('context');
            $table->timestamp('created_at');
            $table->index(['import_run_id', 'line_number'], 'dict_error_line');
        });
    }

    public function down(): void
    {
        // Removal requires an explicit retention/export plan, not accidental loss of provenance.
        throw new RuntimeException('Dictionary storage is forward-only. Export and review retention before removing it.');
    }
};
