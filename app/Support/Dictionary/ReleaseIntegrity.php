<?php

namespace App\Support\Dictionary;

use App\Models\Dictionary\Release;
use Illuminate\Support\Facades\DB;

class ReleaseIntegrity
{
    /** Fingerprint the stored projection, including children, to detect a damaged replay/activation. */
    public function hash(Release $release): string
    {
        $lexemes = DB::table('dictionary_lexemes')->where('release_id', $release->id)->orderBy('id')->get();
        $ids = $lexemes->pluck('id')->all();
        $senses = DB::table('dictionary_senses')->whereIn('lexeme_id', $ids)->orderBy('id')->get();
        $graph = [
            'provenance' => $release->only(['source_id', 'release_key', 'artifact_sha256', 'manifest_sha256', 'adapter_version', 'scope_key', 'artifact_kind', 'source_url', 'upstream_dump_date', 'extracted_at', 'extractor_version', 'license_identifier', 'license_url', 'attribution_text', 'manifest']),
            'lexemes' => $lexemes->all(),
            'senses' => $senses->all(),
            'words' => DB::table('dictionary_words')->whereIn('id', $lexemes->pluck('word_id'))->orderBy('id')->get()->all(),
            'parts_of_speech' => DB::table('dictionary_parts_of_speech')->whereIn('id', $lexemes->pluck('part_of_speech_id'))->orderBy('id')->get()->all(),
        ];
        foreach (['pronunciations', 'forms', 'relations'] as $table) {
            $graph[$table] = DB::table('dictionary_'.$table)->whereIn('lexeme_id', $ids)->orderBy('id')->get()->all();
        }
        foreach (['definitions', 'examples'] as $table) {
            $graph[$table] = DB::table('dictionary_'.$table)->whereIn('sense_id', $senses->pluck('id'))->orderBy('id')->get()->all();
        }

        return hash('sha256', DictionaryText::canonical($graph));
    }
}
