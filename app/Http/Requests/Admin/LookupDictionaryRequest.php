<?php

namespace App\Http\Requests\Admin;

use App\Models\Dictionary\Word;
use App\Support\Dictionary\DictionaryText;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Validator;
use InvalidArgumentException;

class LookupDictionaryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('viewAny', Word::class);
    }

    protected function prepareForValidation(): void
    {
        if ($this->exists('word') && is_string($this->input('word'))) {
            try {
                $this->merge(['word' => DictionaryText::spelling($this->input('word'))]);
            } catch (InvalidArgumentException $exception) {
                throw ValidationException::withMessages(['word' => $exception->getMessage()]);
            }
        }
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return ['word' => ['sometimes', 'required', 'string', 'max:255'], 'language' => ['sometimes', 'required', 'in:en']];
    }

    /** @return list<callable(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            foreach (array_diff(array_keys($this->all()), ['word', 'language']) as $field) {
                $validator->errors()->add($field, 'This query field is not supported.');
            }
        }];
    }
}
