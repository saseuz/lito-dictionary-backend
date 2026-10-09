<?php

namespace App\Http\Requests\Api;

use App\Support\Dictionary\DictionaryText;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class LookupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('word'))) {
            try {
                $this->merge(['word' => DictionaryText::spelling($this->input('word'))]);
            } catch (InvalidArgumentException) {
                throw ValidationException::withMessages(['word' => 'Enter a word of 1–255 characters without control characters.']);
            }
        }
        if (is_string($this->input('language', 'en'))) {
            $this->merge(['language' => strtolower(trim($this->input('language', 'en')))]);
        }
    }

    public function rules(): array
    {
        return ['word' => ['required', 'string', 'max:255'], 'language' => ['required', 'in:en']];
    }
}
