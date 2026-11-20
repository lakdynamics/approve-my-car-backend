<?php

namespace App\Http\Requests\V1\Admin\Checklist;

use App\Enums\AnswerOutcome;
use App\Models\ChecklistAnswerSet;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;

/**
 * { "name": "Condition", "options": [{ "label": "Good", "outcome": "ok" }, ...] }
 */
class StoreAnswerSetRequest extends FormRequest
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

        if (is_array($this->input('options'))) {
            $this->merge(['options' => array_map(
                fn ($option) => is_array($option) && is_string($option['label'] ?? null)
                    ? [...$option, 'label' => Str::squish($option['label'])]
                    : $option,
                $this->input('options'),
            )]);
        }
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100', $this->uniqueName()],
            'options' => ['required', 'array', 'list', 'min:1', 'max:20'],
            'options.*' => ['array:label,outcome'],
            'options.*.label' => ['required', 'string', 'max:50', 'distinct:ignore_case'],
            'options.*.outcome' => ['required', Rule::enum(AnswerOutcome::class)],
        ];
    }

    public function messages(): array
    {
        return [
            'name.unique' => 'An answer list with this name already exists.',
            'options.required' => 'Add at least one option.',
            'options.min' => 'Add at least one option.',
            'options.*.label.distinct' => 'Each option needs a different label.',
        ];
    }

    /**
     * Ignores case (MySQL collation), so "condition" clashes with "Condition".
     */
    protected function uniqueName(?ChecklistAnswerSet $ignore = null): Unique
    {
        return Rule::unique('checklist_answer_sets', 'name')->ignore($ignore);
    }
}
