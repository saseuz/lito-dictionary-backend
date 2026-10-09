<?php

namespace App\Actions\Dictionary;

use App\Models\Dictionary\Language;
use App\Models\Dictionary\Lexeme;
use App\Models\Dictionary\PartOfSpeech;
use App\Models\Dictionary\Release;
use App\Models\Dictionary\SemanticRelation;
use App\Models\Dictionary\Word;
use App\Support\Dictionary\DictionaryText;
use App\Support\Dictionary\FixtureInput;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;
use InvalidArgumentException;
use stdClass;

class WriteKaikkiEntry
{
    private const RELATIONS = ['synonyms' => 'synonym', 'antonyms' => 'antonym', 'hypernyms' => 'hypernym', 'hyponyms' => 'hyponym', 'related' => 'related', 'form_of' => 'form_of', 'alt_of' => 'alternative_of'];

    private const POS = ['noun' => 'Noun', 'verb' => 'Verb', 'adj' => 'Adjective', 'adv' => 'Adverb', 'pron' => 'Pronoun', 'prep' => 'Preposition', 'conj' => 'Conjunction', 'intj' => 'Interjection', 'det' => 'Determiner', 'num' => 'Numeral', 'name' => 'Proper name', 'phrase' => 'Phrase', 'unknown' => 'Unknown'];

    public function validateStructure(stdClass $entry): void
    {
        $this->lists($entry, ['tags', 'senses', 'sounds', 'forms', ...array_keys(self::RELATIONS)]);
        foreach (['senses', 'sounds', 'forms', ...array_keys(self::RELATIONS)] as $field) {
            foreach ($entry->{$field} ?? [] as $item) {
                if (! $item instanceof stdClass) {
                    throw new InvalidArgumentException('Selected record collections must contain JSON objects.');
                }
                $this->lists($item, ['tags']);
                if ($field === 'senses') {
                    $this->lists($item, ['glosses', 'examples', ...array_keys(self::RELATIONS)]);
                    foreach (['examples', ...array_keys(self::RELATIONS)] as $nested) {
                        foreach ($item->{$nested} ?? [] as $child) {
                            if (! $child instanceof stdClass) {
                                throw new InvalidArgumentException('Sense examples and relations must contain JSON objects.');
                            }
                        }
                    }
                }
            }
        }
    }

    /** @param list<string> $fields */
    private function lists(stdClass $object, array $fields): void
    {
        foreach ($fields as $field) {
            if (property_exists($object, $field) && ! is_array($object->{$field})) {
                throw new InvalidArgumentException('Selected list field must be a JSON array: '.$field);
            }
        }
    }

    /** @param array<string, mixed> $data */
    public function validate(array $data): void
    {
        $rules = [
            'word' => ['required', 'string', 'max:255'],
            'lang_code' => ['required', 'in:en'],
            'pos' => ['required', 'string', 'max:64'],
            'original_title' => ['sometimes', 'required', 'string', 'max:255'],
            'etymology_number' => ['sometimes', 'string', 'max:255'],
            'etymology_text' => ['sometimes', 'string', 'max:20000'],
            'senses' => ['sometimes', 'array', 'list', 'max:100'],
            'senses.*' => ['array'],
            'senses.*.glosses' => ['sometimes', 'array', 'list', 'max:100'],
            'senses.*.glosses.*' => ['required', 'string', 'max:20000'],
            'senses.*.examples' => ['sometimes', 'array', 'list', 'max:100'],
            'senses.*.examples.*' => ['array'],
            'senses.*.examples.*.text' => ['required', 'string', 'max:20000'],
            'senses.*.examples.*.type' => ['sometimes', 'string', 'max:64'],
            'senses.*.examples.*.ref' => ['sometimes', 'string', 'max:20000'],
            'sounds' => ['sometimes', 'array', 'list', 'max:100'],
            'sounds.*' => ['array'],
            'sounds.*.ipa' => ['sometimes', 'required', 'string', 'max:1000'],
            'sounds.*.enpr' => ['sometimes', 'required', 'string', 'max:1000'],
            'sounds.*.form' => ['sometimes', 'string', 'max:255'],
            'forms' => ['sometimes', 'array', 'list', 'max:100'],
            'forms.*' => ['array'],
            'forms.*.form' => ['required', 'string', 'max:255'],
        ];
        foreach (['', 'senses.*.', 'sounds.*.', 'forms.*.'] as $prefix) {
            $rules[$prefix.'tags'] = ['sometimes', 'array', 'list', 'max:100'];
            $rules[$prefix.'tags.*'] = ['string', 'max:255'];
        }
        foreach (['', 'senses.*.'] as $prefix) {
            foreach (array_keys(self::RELATIONS) as $field) {
                $rules[$prefix.$field] = ['sometimes', 'array', 'list', 'max:100'];
                $rules[$prefix.$field.'.*'] = ['array'];
                $rules[$prefix.$field.'.*.word'] = ['required', 'string', 'max:255'];
                $rules[$prefix.$field.'.*.lang_code'] = ['sometimes', 'string', 'max:32'];
            }
        }
        Validator::make($data, $rules)->validate();
        DictionaryText::spelling($data['word']);
    }

    /** @param array<string, mixed> $data */
    public function handle(array $data, string $canonical, Release $release, FixtureInput $input, int $line): Lexeme
    {
        $title = $data['original_title'] ?? $data['word'];
        if (! in_array($title, $input->manifest['pages'], true)) {
            throw new InvalidArgumentException('Entry title is missing from the attribution manifest.');
        }
        $language = Language::firstOrCreate(['code' => 'en'], ['name' => 'English']);
        $spelling = DictionaryText::spelling($data['word']);
        $word = Word::firstOrCreate(['language_id' => $language->id, 'headword_hash' => hash('sha256', $spelling)], ['headword' => $spelling]);
        if ($word->headword !== $spelling) {
            throw new InvalidArgumentException('Spelling hash collision. Import refused.');
        }
        $code = array_key_exists($data['pos'], self::POS) ? $data['pos'] : 'unknown';
        $pos = PartOfSpeech::firstOrCreate(['code' => $code], ['name' => self::POS[$code]]);
        $recordHash = hash('sha256', $canonical);
        $lexeme = new Lexeme([
            'word_id' => $word->id, 'release_id' => $release->id, 'part_of_speech_id' => $pos->id,
            'source_record_hash' => $recordHash, 'source_line_number' => $line,
            'source_page_title' => $title, 'source_page_url' => 'https://en.wiktionary.org/wiki/'.rawurlencode(str_replace(' ', '_', $title)).'#English',
            'source_pos' => $data['pos'], 'etymology_key' => $data['etymology_number'] ?? null, 'etymology_text' => $data['etymology_text'] ?? null,
        ]);
        // Keep JSON object/array and numeric types intact, including empty objects and 1.0.
        $lexeme->setRawAttributes([...$lexeme->getAttributes(), 'raw_payload' => $canonical]);
        $lexeme->save();
        foreach ($data['senses'] ?? [] as $i => $rawSense) {
            $sense = $lexeme->senses()->create(['position' => $i, 'metadata' => Arr::except($rawSense, ['examples', ...array_keys(self::RELATIONS)])]);
            foreach ($rawSense['glosses'] ?? [] as $j => $gloss) {
                $sense->definitions()->create(['position' => $j, 'text' => $gloss]);
            }
            foreach ($rawSense['examples'] ?? [] as $j => $example) {
                $review = null;
                foreach ($input->manifest['reviewed_examples'] as $candidate) {
                    if ($candidate['record_sha256'] === $recordHash && $candidate['path'] === "senses.$i.examples.$j" && $candidate['text_sha256'] === hash('sha256', $example['text'])) {
                        $review = $candidate;
                        break;
                    }
                }
                $sense->examples()->create([
                    'position' => $j, 'text' => $example['text'],
                    'kind' => match ($example['type'] ?? '') {
                        'example' => 'usage', 'quote' => 'quotation', default => 'unknown'
                    },
                    'source_reference' => $example['ref'] ?? null,
                    'publication_status' => $review ? 'eligible' : 'withheld',
                    'license_identifier' => $review['license_identifier'] ?? null,
                    'license_url' => $review['license_url'] ?? null,
                    'attribution_text' => $review['attribution_text'] ?? null,
                    'rights_note' => $review['rights_note'] ?? 'Not reviewed for publication.',
                    'metadata' => Arr::except($example, ['text', 'type', 'ref']),
                ]);
            }
            $this->relations($rawSense, $lexeme, $language->id, $sense->id, "senses.$i.");
        }
        foreach ($data['sounds'] ?? [] as $i => $sound) {
            foreach (['ipa', 'enpr'] as $notation) {
                if (isset($sound[$notation])) {
                    $lexeme->pronunciations()->create([
                        'source_path' => "sounds.$i", 'notation' => $notation, 'transcription' => $sound[$notation],
                        'form_text' => $sound['form'] ?? null, 'metadata' => Arr::only($sound, ['tags', 'raw_tags']),
                    ]);
                }
            }
        }
        foreach ($data['forms'] ?? [] as $i => $rawForm) {
            $form = DictionaryText::spelling($rawForm['form']);
            $lexeme->forms()->create(['source_path' => "forms.$i", 'form' => $form, 'form_hash' => hash('sha256', $form), 'metadata' => Arr::except($rawForm, ['form'])]);
        }
        $this->relations($data, $lexeme, $language->id);

        return $lexeme;
    }

    /** @param array<string, mixed> $data */
    private function relations(array $data, Lexeme $lexeme, int $languageId, ?int $senseId = null, string $prefix = ''): void
    {
        foreach (self::RELATIONS as $field => $kind) {
            foreach ($data[$field] ?? [] as $i => $link) {
                // This adapter projects same-language links only; other hints remain in raw JSON.
                if (($link['lang_code'] ?? 'en') !== 'en') {
                    continue;
                }
                $text = DictionaryText::spelling($link['word']);
                SemanticRelation::create([
                    'lexeme_id' => $lexeme->id, 'sense_id' => $senseId, 'source_path' => $prefix.$field.'.'.$i,
                    'kind' => $kind, 'target_language_id' => $languageId, 'target_text' => $text, 'target_hash' => hash('sha256', $text),
                    'metadata' => Arr::except($link, ['word']),
                ]);
            }
        }
    }
}
