<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Dictionary\LookupWord;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\LookupDictionaryRequest;
use Inertia\Inertia;
use Inertia\Response;

class DictionaryController extends Controller
{
    public function lookup(LookupDictionaryRequest $request, LookupWord $lookup): Response
    {
        $word = $request->validated('word');

        return Inertia::render('admin/dictionary/lookup', [
            'query' => $word ?? '',
            ...$lookup->handle($word),
        ]);
    }
}
