<?php

namespace App\Http\Requests\V1\Admin\Checklist;

use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Rename, describe, hide or show a section. Send only what changes.
 */
class UpdateSectionRequest extends StoreSectionRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:100', $this->uniqueName($this->route('section'))],
            'description' => ['sometimes', 'nullable', 'string', 'max:500'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
