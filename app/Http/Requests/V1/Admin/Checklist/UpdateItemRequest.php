<?php

namespace App\Http\Requests\V1\Admin\Checklist;

use App\Models\ChecklistItem;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Edit, hide or move an item. Send only what changes.
 */
class UpdateItemRequest extends StoreItemRequest
{
    protected function prepareForValidation(): void
    {
        parent::prepareForValidation();

        // Moving without a new label: still check the current label is
        // free in the new section.
        if (! $this->has('label') && $this->targetSectionId() !== $this->item()->checklist_section_id) {
            $this->merge(['label' => $this->item()->label]);
        }
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'label' => ['sometimes', 'required', 'string', 'max:150', $this->uniqueLabelIn($this->targetSectionId(), $this->item())],
            'description' => ['sometimes', 'nullable', 'string', 'max:500'],
            'is_active' => ['sometimes', 'boolean'],
            'checklist_section_id' => ['sometimes', 'integer', 'exists:checklist_sections,id'],
        ];
    }

    public function messages(): array
    {
        return [
            ...parent::messages(),
            'checklist_section_id.exists' => 'Select an existing section.',
        ];
    }

    private function item(): ChecklistItem
    {
        return $this->route('item');
    }

    /**
     * The section the item will be in after this update.
     */
    private function targetSectionId(): int
    {
        return is_numeric($this->input('checklist_section_id'))
            ? (int) $this->input('checklist_section_id')
            : $this->item()->checklist_section_id;
    }
}
