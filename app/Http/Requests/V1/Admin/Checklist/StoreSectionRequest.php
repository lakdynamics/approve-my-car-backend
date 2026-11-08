<?php

namespace App\Http\Requests\V1\Admin\Checklist;

use App\Models\ChecklistSection;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;

class StoreSectionRequest extends FormRequest
{
    /**
     * The route's "can:admin" middleware already checked who is calling.
     */
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('name'))) {
            $this->merge(['name' => Str::squish($this->input('name'))]);
        }
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100', $this->uniqueName()],
            'description' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.unique' => 'A section with this name already exists.',
        ];
    }

    /**
     * Ignores case (MySQL collation), so "engine" clashes with "Engine".
     */
    protected function uniqueName(?ChecklistSection $ignore = null): Unique
    {
        return Rule::unique('checklist_sections', 'name')->ignore($ignore);
    }
}
