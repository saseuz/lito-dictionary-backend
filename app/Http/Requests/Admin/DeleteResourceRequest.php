<?php

namespace App\Http\Requests\Admin;

class DeleteResourceRequest extends ManagementRequest
{
    public function authorize(): bool
    {
        $target = $this->route('admin') ?? $this->route('role');

        return $this->user('admin')->can('delete', $target);
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        return ['confirmation' => ['required', 'accepted']];
    }
}
