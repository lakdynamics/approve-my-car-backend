<?php

namespace App\Http\Requests\V1\Admin\Checklist;

use App\Enums\AnswerOutcome;
use App\Models\ChecklistAnswerOption;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

/**
 * Rename an option, change what it means, or hide/show it.
 * Send only what changes.
 */
class UpdateAnswerOptionRequest extends StoreAnswerOptionRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var ChecklistAnswerOption $option */
        $option = $this->route('answerOption');

        return [
            'label' => ['sometimes', 'required', 'string', 'max:50', $this->uniqueLabelIn($option->checklist_answer_set_id, $option)],
            'outcome' => ['sometimes', Rule::enum(AnswerOutcome::class)],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
