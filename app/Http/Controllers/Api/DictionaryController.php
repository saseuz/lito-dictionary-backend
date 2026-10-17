<?php

namespace App\Http\Controllers\Api;

use App\Actions\Dictionary\LookupWord;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\LookupRequest;
use Illuminate\Http\JsonResponse;

class DictionaryController extends Controller
{
    public function __construct(private LookupWord $lookup) {}

    public function __invoke(LookupRequest $request): JsonResponse
    {
        $result = $this->lookup->handle($request->validated('word'), $request->validated('language'));
        if (! $result['available']) {
            return response()->json(['error' => ['code' => 'provider_unavailable', 'message' => 'Dictionary provider unavailable. Please try again later.']], 503);
        }
        if ($result['entry'] === null || ! isset($result['origin'])) {
            return response()->json(['error' => ['code' => 'not_found', 'message' => 'No dictionary entry found.']], 404);
        }
        $entry = $result['entry'];

        // Explicit presentation fields only; never serialize a database model or provider payload.
        return response()->json(['data' => [
            'word' => $entry['headword'], 'language' => $request->validated('language'), 'origin' => $result['origin'],
            'meanings' => array_map(fn ($lexeme) => [
                'part_of_speech' => $lexeme['partOfSpeech'],
                'senses' => array_map(fn ($sense) => [
                    'definitions' => $sense['definitions'],
                    'examples' => array_map(fn ($example) => [
                        'text' => $example['text'], 'attribution' => $example['attribution'] ?? null,
                        'license' => $example['license'] ?? null, 'license_url' => $example['licenseUrl'] ?? null,
                    ], $sense['examples'] ?? []),
                ], $lexeme['senses']),
                'pronunciations' => array_map(fn ($sound) => ['text' => $sound['text'], 'notation' => $sound['notation'], 'audio_url' => $sound['audio'] ?? null], $lexeme['pronunciations'] ?? []),
            ], $entry['lexemes']),
            'source' => [
                'name' => $entry['attribution']['name'] ?? 'Dictionary provider',
                'url' => $entry['attribution']['sourceUrl'], 'attribution' => $entry['attribution']['text'],
                'license' => $entry['attribution']['license'], 'license_url' => $entry['attribution']['licenseUrl'],
            ],
        ]])->header('Cache-Control', 'no-store');
    }
}
