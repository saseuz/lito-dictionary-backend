<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Validator;

abstract class ManagementRequest extends FormRequest
{
    /** @return array<string, array<mixed>> */
    abstract public function rules(): array;

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('email'))) {
            $this->merge(['email' => Str::lower(trim($this->input('email')))]);
        }
        if (is_string($this->input('name'))) {
            $this->merge(['name' => trim($this->input('name'))]);
        }
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function ($validator): void {
            $allowed = array_merge(array_keys($this->rules()), ['_token', '_method']);
            foreach (array_keys($this->all()) as $field) {
                if (! in_array($field, $allowed, true)) {
                    $validator->errors()->add($field, 'This field is not accepted.');
                }
            }
        });
    }
}
