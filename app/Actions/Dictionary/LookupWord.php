<?php

namespace App\Actions\Dictionary;

use App\Models\Dictionary\Example;
use App\Models\Dictionary\Form;
use App\Models\Dictionary\Lexeme;
use App\Models\Dictionary\Pronunciation;
use App\Models\Dictionary\SemanticRelation;
use App\Models\Dictionary\Sense;
use App\Models\Dictionary\Source;
use App\Models\Dictionary\Word;
use App\Support\Dictionary\DictionaryText;

class LookupWord
{
    /** @return array{available: bool, entry: array<string, mixed>|null} */
    public function handle(?string $spelling, string $language = 'en'): array
    {
        $release = Source::where('key', 'wiktionary-en-kaikki')->with('activeRelease')->first()?->activeRelease;
        if (! $release || $release->status !== 'ready') {
            return ['available' => false, 'entry' => null];
        }
        if ($spelling === null || $language !== 'en') {
            return ['available' => true, 'entry' => null];
        }
        $normalized = DictionaryText::spelling($spelling);
        $word = Word::whereHas('language', fn ($query) => $query->where('code', $language))
            ->where('headword_hash', hash('sha256', $normalized))
            ->whereHas('lexemes', fn ($query) => $query->where('release_id', $release->id))
            ->with(['lexemes' => fn ($query) => $query->where('release_id', $release->id)->orderBy('source_line_number'), 'lexemes.partOfSpeech', 'lexemes.senses.definitions', 'lexemes.senses.examples' => fn ($query) => $query->where('publication_status', 'eligible'), 'lexemes.pronunciations', 'lexemes.forms', 'lexemes.relations'])
            ->first();
        if (! $word || $word->headword !== $normalized) {
            return ['available' => true, 'entry' => null];
        }

        return ['available' => true, 'entry' => [
            'headword' => $word->headword, 'language' => 'English',
            'lexemes' => $word->lexemes->map(fn (Lexeme $lexeme): array => [
                'id' => $lexeme->id, 'partOfSpeech' => $lexeme->partOfSpeech->name,
                'etymology' => $lexeme->etymology_text, 'etymologyKey' => $lexeme->etymology_key,
                'sourceUrl' => $lexeme->source_page_url,
                'pronunciations' => $lexeme->pronunciations->map(fn (Pronunciation $sound): array => ['notation' => $sound->notation, 'text' => $sound->transcription, 'form' => $sound->form_text, 'tags' => $this->tags($sound->metadata)])->all(),
                'forms' => $lexeme->forms->map(fn (Form $form): array => ['text' => $form->form, 'tags' => $this->tags($form->metadata)])->all(),
                'relations' => $lexeme->relations->whereNull('sense_id')->map($this->relation(...))->values()->all(),
                'senses' => $lexeme->senses->map(fn (Sense $sense): array => [
                    'id' => $sense->id, 'definitions' => $sense->definitions->pluck('text')->all(), 'tags' => $this->tags($sense->metadata),
                    'examples' => $sense->examples->map(fn (Example $example): array => [
                        'text' => $example->text, 'reference' => $example->source_reference, 'attribution' => $example->attribution_text ?? $release->attribution_text,
                        'license' => $example->license_identifier ?? $release->license_identifier, 'licenseUrl' => $example->license_url ?? $release->license_url,
                    ])->all(),
                    'relations' => $lexeme->relations->where('sense_id', $sense->id)->map($this->relation(...))->values()->all(),
                ])->all(),
            ])->all(),
            'attribution' => [
                'text' => $release->attribution_text, 'license' => $release->license_identifier, 'licenseUrl' => $release->license_url,
                'sourceUrl' => $release->source_url, 'releaseKey' => $release->release_key, 'artifactKind' => $release->artifact_kind,
                'dumpDate' => $release->upstream_dump_date, 'extractorVersion' => $release->extractor_version,
                'transformations' => $release->manifest['transformations'],
            ],
        ]];
    }

    /** @return array{kind: string, text: string, resolved: bool} */
    private function relation(SemanticRelation $relation): array
    {
        return ['kind' => $relation->kind, 'text' => $relation->target_text, 'resolved' => $relation->target_word_id !== null];
    }

    /**
     * @param  array<string, mixed>  $metadata
     * @return list<string>
     */
    private function tags(array $metadata): array
    {
        $tags = $metadata['tags'] ?? [];

        return is_array($tags) ? array_values(array_filter($tags, is_string(...))) : [];
    }
}
