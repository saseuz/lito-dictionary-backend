<?php

use App\Http\Controllers\Api\DictionaryController;
use Illuminate\Support\Facades\Route;

Route::get('v1/dictionary/lookup', DictionaryController::class)
    ->middleware('throttle:public-dictionary');
