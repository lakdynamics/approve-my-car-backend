<?php

namespace App\Http\Requests\V1\Admin\Checklist;

use App\Models\ChecklistItem;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;

class StoreItemRequest extends FormRequest
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
        if (is_string($this->input('label'))) {
            $this->merge(['label' => Str::squish($this->input('label'))]);
        }
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'label' => ['required', 'string', 'max:150', $this->uniqueLabelIn($this->route('section')->id)],
            'description' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'label.unique' => 'This section already has an item with this label.',
        ];
    }

    /**
     * The same label can be used in different sections, not twice in one.
     */
    protected function uniqueLabelIn(int $sectionId, ?ChecklistItem $ignore = null): Unique
    {
        return Rule::unique('checklist_items', 'label')
            ->where('checklist_section_id', $sectionId)
            ->ignore($ignore);
    }
}
