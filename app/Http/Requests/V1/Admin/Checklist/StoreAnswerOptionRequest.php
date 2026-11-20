<?php

namespace App\Http\Requests\V1\Admin\Checklist;

use App\Enums\AnswerOutcome;
use App\Models\ChecklistAnswerOption;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;

/**
 * { "label": "Worn", "outcome": "problem" }
 */
class StoreAnswerOptionRequest extends FormRequest
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
            'label' => ['required', 'string', 'max:50', $this->uniqueLabelIn($this->route('answerSet')->id)],
            'outcome' => ['required', Rule::enum(AnswerOutcome::class)],
        ];
    }

    public function messages(): array
    {
        return [
            'label.unique' => 'This list already has this option.',
        ];
    }

    /**
     * Ignores case (MySQL collation), so "good" clashes with "Good".
     */
    protected function uniqueLabelIn(int $setId, ?ChecklistAnswerOption $ignore = null): Unique
    {
        return Rule::unique('checklist_answer_options', 'label')
            ->where('checklist_answer_set_id', $setId)
            ->ignore($ignore);
    }
}
